<?php
namespace App\Models\Dss;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\RC4;
use App\Models\QRCodeModel;
use App\Models\UserWeiXinModel;
use App\Services\MiniAppQrService;
use App\Services\StudentService;

class DssUserQrTicketModel extends DssModel
{
    public static $table = "user_qr_ticket";
        
    const STUDENT_TYPE = 1;
    const AGENT_TYPE   = 4;

    const LANDING_TYPE_NORMAL  = 1; // 普通Landing页
    const LANDING_TYPE_MINIAPP = 2; // 小程序
    /**
     * 生成用户二维码信息
     * @param $userID
     * @param int $type
     * @param int $channelID
     * @param int $landingType
     * @param array $extParams
     * @return string
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
        if ($type == UserWeiXinModel::USER_TYPE_AGENT) {
            $extParams['c'] = $channelID;
            return MiniAppQrService::getSmartQRAliOss($userID, $type, $extParams)['qr_url'];
        } else {
            $appId = !empty($extParams['app_id']) ? $extParams['app_id'] : Constants::SMART_APP_ID;
            if ($landingType == self::LANDING_TYPE_MINIAPP) {
                $userQrInfo = MiniAppQrService::getUserMiniAppQr(
                    $appId,
                    DssUserWeiXinModel::BUSI_TYPE_REFERRAL_MINAPP,
                    $userID,
                    $type,
                    $channelID,
                    DssUserQrTicketModel::LANDING_TYPE_MINIAPP,
                    [
                        'user_current_status' =>  StudentService::dssStudentStatusCheck($userID)['student_status'],
                        'poster_id' => !empty($extParams['p']) ? $extParams['p'] : 0,
                        'activity_id' => !empty($extParams['a']) ? $extParams['a'] : 0,
                        'employee_id' => !empty($extParams['e']) ? $extParams['e'] : 0,
                    ],
                    true);
                $imagePath = $userQrInfo['qr_path'];
            } else {
                $ticket = RC4::encrypt($_ENV['COOKIE_SECURITY_KEY'], $type . "_" . $userID);
                $imagePath = self::getReferralLandingPageQrImage(
                    [
                        'referee_id'  => $ticket,
                        'channel_id'  => $channelID,
                    ]
                );
            }
        }
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
