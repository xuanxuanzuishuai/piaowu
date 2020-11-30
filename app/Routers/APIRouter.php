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
        '/api/consumer/referee_award' => [
            'method' => ['post'],
            'call' => Consumer::class . ':refereeAward',
            'middles' => [],
        ]
    ];
}