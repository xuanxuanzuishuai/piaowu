<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/10/29
 * Time: 20:04
 */
namespace App\Routers;

use App\Middleware\OpernMiniAppAuthCheckMiddleware;
use App\Middleware\OpernMiniAppBackupAuthCheckMiddleware;
use App\Controllers\OpernMiniapp\Message;

class OpernMinAppRouter extends RouterBase
{
    protected $logFilename = 'dss_opern_minapp.log';

    protected $uriConfig = [
        '/opern_miniapp/message/notify' => [
            'method'  => ['get', 'post'],
            'call'    => Message::class . ':notify',
            'middles' => [OpernMiniAppAuthCheckMiddleware::class]
        ],
        '/opern_miniapp/message/notify_backup' => [
            'method'  => ['get', 'post'],
            'call'    => Message::class . ':notifyBackup',
            'middles' => [OpernMiniAppBackupAuthCheckMiddleware::class]
        ]
    ];

}