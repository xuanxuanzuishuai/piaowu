<?php
namespace App\Models\Dss;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Dss;
use App\Libs\RC4;
use App\Models\QRCodeModel;
use App\Services\PosterService;

class DssUserQrTicketModel extends DssModel
{
    public static $table = "user_qr_ticket";
        
    const STUDENT_TYPE = 1;
    
    const LANDING_TYPE_NORMAL  = 1; // 普通Landing页
    const LANDING_TYPE_MINIAPP = 2; // 小程序

    /**
     * 获取用户二维码图片
     * @param $userID
     * @param int $type
     * @param int $channelID
     * @param int $landingType
     * @param array $extParams
     * @return mixed|string
     * @throws \App\Libs\Exceptions\RunTimeException
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public static function getUserQrURL(
        $userID,
        $type = self::STUDENT_TYPE,
        $channelID = 0,
        $landingType = self::LANDING_TYPE_NORMAL,
        $extParams = []
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
                AND `landing_type` = :landing_type 
                ";

        $map = [];
        $map[':user_id']      = $userID;
        $map[':channel_id']   = $channelID;
        $map[':type']         = $type;
        $map[':landing_type'] = $landingType;

        $extParamsDict = [
            'activity_id' => $extParams['a'] ?? 0,
            'employee_id' => $extParams['e'] ?? 0,
            'poster_id'   => $extParams['p'] ?? 0,
            'app_id'      => $extParams['app_id'] ?? Constants::SMART_APP_ID,
        ];
        foreach ($extParamsDict as $key => $value) {
            $sql .= " AND ext->>'$." . $key . "' = " . $value;
        }
        $userTicket = self::dbRO()->queryAll(sprintf($sql, self::getTableNameWithDb()), $map);
        if (!empty($userTicket[0]['qr_url'])) {
            return $userTicket[0]['qr_url'];
        }

        $ticket = RC4::encrypt($_ENV['COOKIE_SECURITY_KEY'], $type . "_" . $userID);
        if ($landingType == self::LANDING_TYPE_MINIAPP) {
            $imagePath = PosterService::getMiniappQrImage(
                Constants::SMART_APP_ID,
                array_merge(
                    [
                        'r'       => $ticket,
                        'c'       => $channelID,
                        'app_id'  => Constants::SMART_APP_ID,
                        'type'    => $type,
                        'user_id' => $userID,
                    ],
                    $extParams
                )
            );
        } else {
            $imagePath = self::getReferralLandingPageQrImage(
                [
                    'referee_id'  => $ticket,
                    'channel_id'  => $channelID,
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
                'ext'          => json_encode($extParamsDict)
            ]
        );
        return $imagePath;
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
