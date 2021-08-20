<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/8/16
 * Time: 2:56 PM
 */

namespace App\Routers;

use App\Controllers\API\Consumer;

class APIRouter extends RouterBase
{
    protected $logFilename = 'operation_api.log';

    protected $uriConfig = [
        '/api/consumer/wechat_mini_update' => [
            'method' => ['post'],
            'call' => Consumer::class . ':updateAccessToken',
            'middles' => [],
        ],
        '/api/consumer/referee_relate' => [
            'method' => ['post'],
            'call' => Consumer::class . ':refereeAward',
            'middles' => [],
        ],
        '/api/consumer/red_pack' => [
            'method' => ['post'],
            'call' => Consumer::class . ':redPackDeal',
            'middles' => [],
        ],
        '/api/consumer/operation_message' => [
            'method' => ['post'],
            'call' => Consumer::class . ':pushMessage',
            'middles' => [],
        ],
        '/api/consumer/send_duration' => [
            'method' => ['post'],
            'call' => Consumer::class . ':sendDuration',
            'middles' => [],
        ],
        '/api/consumer/third_part_bill' => [
            'method' => ['post'],
            'call' => Consumer::class . ':thirdPartBill',
            'middles' => [],
        ],
        '/api/consumer/wechat' => [
            'method' => ['post'],
            'call' => Consumer::class . ':wechatConsumer',
            'middles' => [],
        ],
        '/api/consumer/student_account_award_points' => [
            'method' => ['post'],
            'call' => Consumer::class . ':studentAccountAwardPoints',
            'middles' => [],
         ],
        '/api/consumer/points_exchange_red_pack' => [
            'method' => ['post'],
            'call' => Consumer::class . ':pointsExchangeRedPack',
            'middles' => [],
        ],
        '/api/consumer/save_ticket' => [
            'method' => ['post'],
            'call' => Consumer::class . ':saveTicket',
            'middles' => [],
        ],
        '/api/consumer/check_poster' => [
            'method' => ['post'],
            'call' => Consumer::class . ':checkPoster',
            'middles' => [],
        ],
        '/api/consumer/grant_award' => [
            'method' => ['post'],
            'call' => Consumer::class . ':grantAward',
            'middles' => [],
        ],
        '/api/consumer/agent' => [
            'method' => ['post'],
            'call' => Consumer::class . ':agent',
            'middles' => [],
        ],
        '/api/consumer/week_activity' => [
            'method' => ['post'],
            'call' => Consumer::class . ':weekWhiteGrandLeaf',
            'middles' => [],
        ],
    ];
}