<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/5/10
 * Time: 15:13
 */

namespace App\Controllers\StudentOrgWX;

use App\Services\UserService;
use App\Services\WeChatService;
use App\Models\UserWeixinModel;
use App\Libs\UserCenter;

class WeChatMsgHandler
{
    /**
     * 用户关注公众号
     * @param $xml
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function subscribe($xml){
        $userOpenId = (string)$xml->FromUserName;
        $boundInfo = UserWeixinModel::getRecord(["open_id" => $userOpenId], '*', false);
        if (!empty($boundInfo)){
            $result = "您已经绑定成功！";
        } else {
            $url = $_ENV["WECHAT_FRONT_DOMAIN"] . "/bind/org/add";
            $result = '您未绑定，请点击<a href="' . $url . '"> 绑定 </a>。';
        }
        WeChatService::notifyUserWeixinTextInfo(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            UserWeixinModel::USER_TYPE_STUDENT_ORG, $userOpenId, $result);
    }

    /**
     * @param $xml
     * @return bool
     * @throws \App\Libs\KeyErrorRC4Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     * 自定义点击事件
     */
    public static function menuClickEventHandler($xml)
    {
        // 自定义KEY事件
        $keyEvent = (string)$xml->EventKey;
        // 事件发送者
        $userOpenId = (string)$xml->FromUserName;
        if ($keyEvent == "PUSH_MSG_USER_SHARE") {
            $user = UserWeixinModel::getBoundInfoByOpenId($userOpenId);
            if (empty($user)) {
                $url = $_ENV["WECHAT_FRONT_DOMAIN"] . "/bind/org/add";
                $result = '您未绑定，请点击<a href="' . $url . '"> 绑定 </a>。';
                WeChatService::notifyUserWeixinTextInfo(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
                    UserWeixinModel::USER_TYPE_STUDENT_ORG, $userOpenId, $result);
            } else {
                $actKey = $_ENV['ACT_QR_KEY'];
                $newFilename = $newFilename = substr(md5($user['user_id']), 8, 16) . '.png';
                $posterFile = PROJECT_ROOT . '/www/act/' . $actKey . '/my-poster.jpg';
                $myPosterFile = UserService::generateQRPoster($user['user_id'], "act/U" . $actKey, $newFilename, $posterFile);
                $absPosterFile = $_ENV['STATIC_FILE_SAVE_PATH'] . $myPosterFile;
                $data = WeChatService::uploadImg($absPosterFile);
                if (!empty($data['media_id'])) {
                    WeChatService::commonWeixinAPI(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, UserWeixinModel::USER_TYPE_STUDENT_ORG,'POST', 'message/custom/send', ['touser' => $userOpenId, 'msgtype' => 'image', 'image' => ['media_id' => $data['media_id']]]);
                }

            }
        }
        return false;
    }
}
