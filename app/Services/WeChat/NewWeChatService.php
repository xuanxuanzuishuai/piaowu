<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/7/31
 * Time: 6:30 PM
 */

namespace App\Services\WeChat;


use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\UserWeixinModel;

class NewWeChatService
{
    /**
     * @return WeChatMiniPro
     */
    public static function getWX()
    {
        $config = [
            'app_id' => $_ENV['STUDENT_WEIXIN_APP_ID'],
            'app_secret' => $_ENV['STUDENT_WEIXIN_APP_SECRET'],
        ];
        $wx = WeChatMiniPro::factory($config);
        if (empty($wx)) {
            SimpleLogger::error('wx mini pro create fail', ['config' => $config]);
        }
        return $wx;
    }

    /**
     * 队列推送
     * @param $msgBody
     */
    public static function queuePush($msgBody)
    {
        $wx = self::getWX();
        //延时发放存在进入消息队列时openid未存在，但在发送消息时可能已存在，故作此判断
        self::checkOpenId($msgBody);
        if (empty($msgBody['open_id'])) {
            SimpleLogger::error("push wx message error", ['msg_body' => $msgBody, 'openid' => $msgBody['open_id']]);
        } else {
            switch ($msgBody['wx_push_type']) {
                case 'template':
                    $wx->templateSend($msgBody['open_id'],
                        $msgBody['template_id'],
                        $msgBody['data'],
                        $msgBody['url'] ?? NULL);
                    break;
                case 'text':
                    $wx->sendText($msgBody['open_id'], $msgBody['content']);
                    break;
                case 'image':
                    $media = $wx->getTempMedia('image', $msgBody['image_key'], $msgBody['image_url']);
                    $wx->sendImage($msgBody['open_id'], $media['media_id']);
                    break;
                default:
                    SimpleLogger::error('invalid wx_push_type', ['$msgBody' => $msgBody]);
            }
        }
    }

    /**
     * 检测openid是否存在
     * @param $msgBody
     */
    private static function checkOpenId(&$msgBody)
    {
        //延时发放存在进入消息队列时openid未存在，但在发送消息时可能已存在，故作此判断
        if (empty($msgBody['open_id']) && !empty($msgBody['student_id'])) {
            $userOpenIdInfo = UserWeixinModel::getBoundUserIds([$msgBody['student_id']], UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT);
            $msgBody['open_id'] = $userOpenIdInfo[0]['open_id'];
        }
    }
}