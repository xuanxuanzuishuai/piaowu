<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/5/10
 * Time: 15:13
 */

namespace App\Controllers\StudentOrgWX;

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
        $boundInfo = UserWeixinModel::getRecord(["open_id" => $userOpenId]);
        if (!empty($boundInfo)){
            $result = "您已经绑定成功！";
        } else {
            $url = $_ENV["WECHAT_FRONT_DOMAIN"] . "/bind/org/add";
            $result = '您未绑定，请点击<a href="' . $url . '"> 绑定 </a>。';
        }
        WeChatService::notifyUserWeixinTextInfo(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            UserWeixinModel::USER_TYPE_STUDENT_ORG, $userOpenId, $result);
    }
}
