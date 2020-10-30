<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/10/29
 * Time: 20:10
 */

namespace App\Services\WeChat;

use App\Libs\AliOSS;
use App\Libs\SimpleLogger;
use App\Libs\WeChat\WeChatMiniPro;

class OpernMiniAppService
{
    // 自动回复二维码图片路径(oss)
    const AUTO_REPLAY_QR_CODE_PATH = '/miniapp_code/opern_miniapp_qr_code.png';

    public static function handler($message)
    {
        return self::miniprogrampage($message);
    }

    private static function miniprogrampage($message)
    {
        $config = [
            'app_id'     => $_ENV['OPERN_MINI_APP_ID'],
            'app_secret' => $_ENV['OPERN_MINI_APP_SECRET'],
        ];
        $wx = WeChatMiniPro::factory($config);
        if (empty($wx)) {
            SimpleLogger::error('wx mini pro create fail', ['config' => $config]);
            return true;
        }
        $filePath = $_ENV['ENV_NAME'].self::AUTO_REPLAY_QR_CODE_PATH;
        $media    = $wx->getTempMedia('image', $filePath, AliOSS::replaceCdnDomainForDss($filePath));
        if (!empty($media['media_id'])) {
            $wx->sendImage($message['FromUserName'], $media['media_id']);
        }
        return true;
    }
}
