<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/10/29
 * Time: 20:10
 */

namespace App\Services\WeChat;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Libs\WeChat\WeChatMiniPro;
use App\Services\DictService;

class OpernMiniAppService
{
    public static function handler($message, $isBackup = false)
    {
        switch ($message['MsgType']) {
            // 除【事件】外，其他类型消息均回复二维码
            case 'text':
            case 'image':
            case 'miniprogrampage':
                return self::miniprogrampage($message, $isBackup);
                break;
            
            default:
                break;
        }
        return false;
    }

    private static function miniprogrampage($message, $isBackup = false)
    {
        $config = [
            'app_id'     => $_ENV['OPERN_MINI_APP_ID'],
            'app_secret' => $_ENV['OPERN_MINI_APP_SECRET'],
        ];
        if ($isBackup) {
            $config = [
                'app_id'     => $_ENV['OPERN_MINI_BACKUP_APP_ID'],
                'app_secret' => $_ENV['OPERN_MINI_BACKUP_APP_SECRET'],
            ];
        }

        $wx = WeChatMiniPro::factory($config);
        if (empty($wx)) {
            SimpleLogger::error('wx mini pro create fail', ['config' => $config]);
            return true;
        }
        list($path, $url) = DictService::getKeyValuesByArray(Constants::DICT_TYPE_OPERN_MINIAPP_INFO, ['wuxianpu_code_path', 'wuxianpu_code_url']);
        $media = $wx->getTempMedia('image', $path, $url);
        if (!empty($media['media_id'])) {
            $wx->sendImage($message['FromUserName'], $media['media_id']);
        }
        return true;
    }

    /**
     * 获取识谱大作战小程序原始ID
     * @return mixed
     */
    public static function getRawID()
    {
        return DictService::getKeyValue(Constants::DICT_TYPE_OPERN_MINIAPP_INFO, 'raw_app_id');
    }
    
}
