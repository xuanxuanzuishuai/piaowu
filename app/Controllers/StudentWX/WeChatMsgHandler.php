<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/02/14
 * Time: 16:35
 */

namespace App\Controllers\StudentWX;

use App\Libs\DictConstants;
use App\Libs\RedisDB;
use App\Models\UserQrTicketModel;
use App\Models\WeChatOpenIdListModel;
use App\Services\UserService;
use App\Services\WeChatService;
use App\Models\UserWeixinModel;
use App\Models\WeChatConfigModel;
use App\Models\PosterModel;
use App\Libs\UserCenter;
use App\Libs\Util;

class WeChatMsgHandler
{
    /**
     * 用户关注公众号
     * @param $xml
     */
    public static function subscribe($xml)
    {
        $userOpenId = (string)$xml->FromUserName;
        //获取后台设置的关注回复内容数据
        $contentInfo = WeChatConfigModel::getRecords(["msg_type" => (string)$xml->MsgType,"event_type" => (string)$xml->Event, "type" => WeChatConfigModel::WECHAT_TYPE_STUDENT], ['content', 'content_type'], false);
        if ($contentInfo) {
            foreach ($contentInfo as $cval) {
                if ($cval['content_type'] == WeChatConfigModel::CONTENT_TYPE_TEXT) {
                    //转义数据
                    $content = Util::textDecode($cval['content']);
                    //发送文本消息
                    WeChatService::notifyUserWeixinTextInfo(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
                        UserWeixinModel::USER_TYPE_STUDENT, $userOpenId, $content);
                } elseif ($cval['content_type'] == WeChatConfigModel::CONTENT_TYPE_IMG) {
                    //发送图片消息
                    WeChatService::toNotifyUserWeixinCustomerInfoForImage(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, UserWeixinModel::USER_TYPE_STUDENT, $userOpenId, $cval['content']);
                }
            }
        }
        //关注记录
        WeChatService::weChatOpenIdSubscribeRelate($userOpenId, WeChatOpenIdListModel::SUBSCRIBE_WE_CHAT, UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, UserWeixinModel::USER_TYPE_STUDENT, UserWeixinModel::BUSI_TYPE_STUDENT_SERVER);
    }

    /**
     * @param $xml
     * 用户取消关注公众号
     */
    public static function unSubscribe($xml)
    {
        $userOpenId = (string)$xml->FromUserName;
        //取消关注记录
        WeChatService::weChatOpenIdSubscribeRelate($userOpenId, WeChatOpenIdListModel::UNSUBSCRIBE_WE_CHAT, UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, UserWeixinModel::USER_TYPE_STUDENT, UserWeixinModel::BUSI_TYPE_STUDENT_SERVER);
    }

    /**
     * 自定义点击事件
     * @param $xml
     * @return bool
     */
    public static function menuClickEventHandler($xml)
    {
        // 自定义KEY事件
        $keyEvent = (string)$xml->EventKey;
        // 事件发送者
        $userOpenId = (string)$xml->FromUserName;
        if ($keyEvent == "STUDENT_PUSH_MSG_USER_SHARE") {
            //学生转介绍学生
            $user = UserWeixinModel::getBoundInfoByOpenId(
                $userOpenId,
                UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
                WeChatService::USER_TYPE_STUDENT,
                UserWeixinModel::BUSI_TYPE_STUDENT_SERVER
            );
            if (empty($user)) {
                //未绑定
                $url = $_ENV["REFERRER_REGISTER_URL"];
                $result = '您未绑定，请点击<a href="' . $url . '"> 绑定 </a>。';
                WeChatService::notifyUserWeixinTextInfo(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
                    UserWeixinModel::USER_TYPE_STUDENT, $userOpenId, $result);
            } else {
                //获取转介绍配置数据
                $referralConfig = PosterModel::getRecord(["apply_type" => PosterModel::APPLY_TYPE_STUDENT_WECHAT, "status" => PosterModel::STATUS_PUBLISH, "poster_type" => PosterModel::POSTER_TYPE_WECHAT_STANDARD], ['url', 'settings', 'content1', 'content2'], false);
                if ($referralConfig) {
                    //发送文字内容
                    $content[] = Util::textDecode($referralConfig['content1']);
                    $content[] = Util::textDecode($referralConfig['content2']);
                    foreach ($content as $cv) {
                        if ($cv) {
                            WeChatService::notifyUserWeixinTextInfo(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
                                UserWeixinModel::USER_TYPE_STUDENT, $userOpenId, $cv);
                        }
                    }
                    $settings = json_decode($referralConfig['settings'], true);
                    //生成二维码海报
                    $posterImgFile = UserService::generateQRPosterAliOss($user['user_id'], $referralConfig['url'], UserQrTicketModel::STUDENT_TYPE, $settings['poster_width'], $settings['poster_height'], $settings['qr_width'], $settings['qr_height'], $settings['qr_x'], $settings['qr_y'], DictConstants::get(DictConstants::STUDENT_INVITE_CHANNEL, 'NORMAL_STUDENT_INVITE_STUDENT'));
                    if(!empty($posterImgFile)){
                        //上传到微信服务器
                        $data = WeChatService::uploadImg($posterImgFile,UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,UserWeixinModel::USER_TYPE_STUDENT);
                        //发送海报
                        if (!empty($data['media_id'])) {
                            WeChatService::toNotifyUserWeixinCustomerInfoForImage(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, UserWeixinModel::USER_TYPE_STUDENT, $userOpenId, $data['media_id']);
                        }
                    }
                }
            }
        } else if ($keyEvent == 'AI_SERVER_TEL'){ //客服电话
            $tel = $_ENV["AI_SERVER_TEL"];
            WeChatService::notifyUserWeixinTextInfo(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
                UserWeixinModel::USER_TYPE_STUDENT, $userOpenId, $tel);
        }
        return false;
    }

    //serverOnline计算当前时间是否在指定时间段内；$fragment格式：12:00-21:00,22:01-22:02,...
    private static function serverOnline($fragment)
    {
        $fs = explode(',', $fragment);
        if(empty($fs)) {
            return false;
        }
        $now = time();
        foreach($fs as $f) {
            $s = explode('-', $f);
            if(count($s) == 2 && $now >= strtotime($s[0]) && $now <= strtotime($s[1])) {
                return true;
            }
        }
        return false;
    }

    //autoReply在客服离线时自动回复客服消息，1小时内只回复1次
    public static function autoReply($xml)
    {
        if(!empty($_ENV['SERVER_AUTO_REPLY']) && !self::serverOnline($_ENV['SERVER_ONLINE_TIME'])) {
            $client = (string)$xml->FromUserName;
            $key = "{$client}.last_send_time";
            $db = RedisDB::getConn();
            if(empty($db->get($key))) {
                $reply = "您好，微信平台客服在线服务时间为{$_ENV['SERVER_ONLINE_TIME']}，非在线时间咨询可拨打客服电话 400-029-2609";
                WeChatService::notifyUserWeixinTextInfo(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
                    UserWeixinModel::USER_TYPE_STUDENT, $client, $reply);
                $db->setex($key, 3600, time());
            }
        }
    }
}
