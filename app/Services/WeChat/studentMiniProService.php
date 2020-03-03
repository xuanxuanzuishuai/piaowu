<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/3/3
 * Time: 4:40 PM
 */

namespace App\Services\WeChat;


use App\Libs\SimpleLogger;
use App\Libs\WeChat\WeChatMiniPro;

class studentMiniProService
{
    public static function handler($message)
    {
        switch ($message['MsgType']) {
            case 'miniprogrampage':
                $ret = self::miniprogrampage($message);
                break;
            default:
                $ret = true;
        }
        return $ret;
    }

    private static function miniprogrampage($message)
    {
        if ($message['AppId'] !== $_ENV['STUDENT_MINIPRO_APP_ID']) {
            return true;
        }

        $config = [
            'app_id' => $_ENV['STUDENT_MINIPRO_APP_ID'],
            'app_secret' => $_ENV['STUDENT_MINIPRO_APP_SECRET'],
        ];
        $wx = WeChatMiniPro::factory($config);
        if (empty($wx)) {
            SimpleLogger::error('wx mini pro create fail', ['config' => $config]);
        }

        $wx->sendText($message['FromUserName'], 'hello');
        return true;
    }
}