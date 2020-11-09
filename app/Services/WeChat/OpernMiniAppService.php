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
    // 自动回复二维码图片路径(oss)
    const AUTO_REPLAY_QR_CODE_PATH = '/miniapp_code/opern_miniapp_qr_code.png';
    // 备用小程序消息推送图片路径
    const AUTO_REPLAY_QR_CODE_BACKUP_PATH = '/miniapp_code/opern_miniapp_qr_code_backup.png';

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
        $filePath = $_ENV['ENV_NAME'].self::AUTO_REPLAY_QR_CODE_PATH;
        
        if ($isBackup) {
            $config = [
                'app_id'     => $_ENV['OPERN_MINI_BACKUP_APP_ID'],
                'app_secret' => $_ENV['OPERN_MINI_BACKUP_APP_SECRET'],
            ];
            $filePath = $_ENV['ENV_NAME'].self::AUTO_REPLAY_QR_CODE_BACKUP_PATH;
        }

        $wx = WeChatMiniPro::factory($config);
        if (empty($wx)) {
            SimpleLogger::error('wx mini pro create fail', ['config' => $config]);
            return true;
        }
        $media = $wx->getTempMedia('image', $filePath, AliOSS::replaceCdnDomainForDss($filePath));
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
