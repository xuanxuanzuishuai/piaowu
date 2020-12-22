<?php
namespace App\Models\Dss;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Dss;
use App\Libs\RC4;
use App\Libs\SimpleLogger;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\QRCodeModel;
use App\Services\ReferralActivityService;

class DssUserQrTicketModel extends DssModel
{
    public static $table = "user_qr_ticket";
        
    const STUDENT_TYPE = 1;
    
    const LANDING_TYPE_NORMAL  = 1; // 普通Landing页
    const LANDING_TYPE_MINIAPP = 2; // 小程序

    /**
     * 获取用户二维码图片
     * @param $userID
     * @param $channelID
     * @param null $activityID
     * @param null $employeeID
     * @param null $appID
     * @param int $landingType
     * @param int $type
     * @return mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public static function getUserQrURL(
        $userID,
        $channelID,
        $activityID = null,
        $employeeID = null,
        $appID = null,
        $landingType = self::LANDING_TYPE_NORMAL,
        $type = self::STUDENT_TYPE,
        $posterId = 0
    ) {

        $sql = "
            SELECT 
                user_id,
                qr_ticket,
                qr_url,
                channel_id,
                type,
                create_time,
                landing_type,
                ext
            FROM %s
            WHERE 
                `user_id` = :user_id
                AND `channel_id` = :channel_id
                AND `type` = :type
                AND `landing_type` = :landing_type ";
        $map = [];
        $map[':user_id']      = $userID;
        $map[':channel_id']   = $channelID;
        $map[':type']         = $type;
        $map[':landing_type'] = $landingType;

        if (!empty($activityID)) {
            $sql .= "
                AND ext->>'$.activity_id' = :activity_id
                AND ext->>'$.employee_id' = :employee_id
                AND ext->>'$.app_id' = :app_id
            ";
            $map[':activity_id'] = $activityID;
            $map[':employee_id'] = $employeeID;
            $map[':app_id'] = $appID;
        }
        $userTicket = self::dbRO()->queryAll(sprintf($sql, self::getTableNameWithDb()), $map);
        if (!empty($userTicket[0]['qr_url'])) {
            return $userTicket[0]['qr_url'];
        }

        $ticket = RC4::encrypt($_ENV['COOKIE_SECURITY_KEY'], $type . "_" . $userID);
        if ($landingType == self::LANDING_TYPE_MINIAPP) {
            $imagePath = self::getMiniappQrImage(
                Constants::SMART_APP_ID,
                [
                    'r'       => $ticket,
                    'c'       => $channelID,
                    'a'       => $activityID,
                    'e'       => $employeeID,
                    'p'       => $posterId,
                    'app_id'  => Constants::SMART_APP_ID,
                    'type'    => $type,
                    'user_id' => $userID,
                ]
            );
        } else {
            $imagePath = self::getReferralLandingPageQrImage(
                [
                    'referee_id'  => $ticket,
                    'activity_id' => $activityID,
                    'employee_id' => $employeeID,
                    'channel_id'  => $channelID,
                    'poster_id'   => $posterId,
                ]
            );
        }
        // INSERT NEW TICKET DATA INTO DSS
        (new Dss())->saveTicket(
            [
                'user_id'      => $userID,
                'qr_ticket'    => $ticket,
                'qr_url'       => $imagePath,
                'channel_id'   => $channelID,
                'type'         => $type,
                'landing_type' => $landingType,
                'ext'          => json_encode(['activity_id' => $activityID, 'employee_id' => $employeeID, 'app_id' => $appID])
            ]
        );
        return $imagePath;
    }

    /**
     * 获取小程序码图片
     * @param $appid
     * @param array $params
     * @return string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function getMiniappQrImage($appid, $params = [])
    {
        $userQrTicket = $params['r'] ?? '';
        $wx           = WeChatMiniPro::factory($appid, Constants::SMART_MINI_BUSI_TYPE);
        if (empty($wx)) {
            SimpleLogger::error('wx create fail', ['getMiniappQrImage'=>$params]);
            return '';
        }
        // 请求微信，获取小程序码图片
        $paramsId = ReferralActivityService::getParamsId($params);
        $res = $wx->getMiniappCodeImage($paramsId);
        if ($res === false) {
            SimpleLogger::error('get mini app code image error', [$res, $params]);
            return '';
        }
        $tmpFileFullPath = $_ENV['STATIC_FILE_SAVE_PATH'] . '/' . md5($userQrTicket) . '.jpg';
        chmod($tmpFileFullPath, 0755);

        $bytesWrite = file_put_contents($tmpFileFullPath, $res);
        if (empty($bytesWrite)) {
            SimpleLogger::error('save miniapp code image file error', [$userQrTicket]);
            return '';
        }
        $imageUrl = $_ENV['ENV_NAME'] . '/' . AliOSS::DIR_MINIAPP_CODE . '/' . md5(implode(',', $params)) . ".png";
        AliOSS::uploadFile($imageUrl, $tmpFileFullPath);
        unlink($tmpFileFullPath);
        return $imageUrl;
    }

    /**
     * 获取Landing页二维码
     * @param $params
     * @return string
     */
    public static function getReferralLandingPageQrImage($params)
    {
        //生成二维码
        $content = $_ENV["AI_REFERRER_URL"] . "?" . http_build_query($params);
        list($filePath, $fileName) = QRCodeModel::genImage($content, time());
        chmod($filePath, 0755);
        //上传二维码到阿里oss
        $envName  = $_ENV['ENV_NAME'] ?? 'dev';
        $imageUrl = $envName . '/' . AliOSS::DIR_REFERRAL . '/' . $fileName;
        AliOSS::uploadFile($imageUrl, $filePath);
        //删除临时二维码文件
        unlink($filePath);
        
        return $imageUrl;
    }
}
