<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/8/16
 * Time: 2:56 PM
 */

namespace App\Routers;

use App\Controllers\API\Consumer;
use App\Controllers\API\Track;
use App\Controllers\API\BAIDU;
use App\Controllers\API\WeChat;

class APIRouter extends RouterBase
{
    protected $logFilename = 'dss_api.log';

    protected $uriConfig = [
        '/api/track/ad_event/ocean_engine' => [
            'method' => ['get'],
            'call' => Track::class . ':adEventOceanEngine',
            'middles' => [],
        ],
        '/api/track/ad_event/gdt' => [
            'method' => ['get'],
            'call' => Track::class . ':adEventGdt',
            'middles' => [],
        ],
        '/api/track/ad_event/wx' => [
            'method' => ['get'],
            'call' => Track::class . ':adEventWx',
            'middles' => [],
        ],
        '/api/track/idfaQueryApi' => [
            'method' => ['get'],
            'call' => Track::class . ':checkIdfa',
            'middles' => []
        ],
        '/api/track/idfaClickApi' => [
            'method' => ['post'],
            'call' => Track::class . ':clickIdfa',
            'middles' => []
        ],
        //百度文字转语音
        '/api/baidu/audio_token' => [
            'method'  => ['get'],
            'call'    => BAIDU::class . ':audioToken',
            'middles' => [],
        ],

        '/api/consumer/channel_status' => [
            'method' => ['post'],
            'call' => Consumer::class . ':channelStatus',
        ],
        '/api/consumer/user_play' => [
            'method' => ['post'],
            'call' => Consumer::class . ':userPlay',
        ],

        '/api/consumer/push_message' => [
            'method' => ['post'],
            'call' => Consumer::class . ':pushMessage'
        ],

        '/api/consumer/table_sync' => [
            'method' => ['post'],
            'call' => Consumer::class . ':tableSync'
        ],

        '/api/we_chat/student_minipro' => [
            'method' => ['post'],
            'call' => WeChat::class . ':studentMiniPro',
            'middles' => [],
        ],
    ];
}