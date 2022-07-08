<?php

namespace App\Routers\Client;

use App\Controllers\Client\Activity\LimitTimeActivityController;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Middleware\Client\ClientAuthMiddleware;
use App\Routers\RouterBase;

class ClientRouter extends RouterBase
{
    protected $logFilename = 'operation_client.log';
    public $middleWares = [ClientAuthMiddleware::class];
    //路由前缀
    public $prefix = '/client';
    //路由标识与路由文件映射关系数组
    public $routerPathMap = [
        'limit_time_activity' => PROJECT_ROOT . '/app/Routers/Client/activity/limitTimeActivityRouter.php',
    ];
    //uri配置文件
    protected $uriConfig = [];

    public function __construct($uriPath)
    {
        $uriPath = str_replace($this->prefix, '', $uriPath);
        $uriPathData = explode('/', $uriPath);
        if (!empty($this->routerPathMap[$uriPathData[1]])) {
            $this->uriConfig = require_once $this->routerPathMap[$uriPathData[1]];
        }
    }
}