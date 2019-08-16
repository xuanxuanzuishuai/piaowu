<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/8/16
 * Time: 2:56 PM
 */

namespace App\Routers;

use App\Controllers\Admin\Menu;
use App\Middleware\AdminMiddleware;

class AdminRouter extends RouterBase
{
    public $middleWares = [AdminMiddleware::class];
    protected $logFilename = 'dss_admin.log';
    protected $uriConfig = [
        '/admin/menu/main' => [
            'method' => ['get'],
            'call' => Menu::class . ':main',
            'middles' => [],
        ],
        '/admin/menu/page' => [
            'method' => ['get'],
            'call' => Menu::class . ':page',
        ],
        '/admin/menu/process' => [
            'method' => ['post'],
            'call' => Menu::class . ':process',
        ]
    ];
}