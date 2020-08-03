<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/7/31
 * Time: 6:30 PM
 */

namespace App\Services;


use App\Libs\SimpleLogger;
use App\Libs\WeChat\WeChatMiniPro;

class NewWeChatService
{
    /**
     * @return WeChatMiniPro
     */
    public static function getWX()
    {
        $config = [
            'app_id' => $_ENV['STUDENT_MINIPRO_APP_ID'],
            'app_secret' => $_ENV['STUDENT_MINIPRO_APP_SECRET'],
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