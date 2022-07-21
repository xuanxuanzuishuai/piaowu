<?php

namespace App\Routers\Client;

use App\Middleware\Client\ClientAuthMiddleware;
use App\Routers\RouterBase;

class ClientRouter extends RouterBase
{
    protected $logFilename = 'operation_client.log';
    public $middleWares = [ClientAuthMiddleware::class];
    //路由前缀
    private $firstLevelPrefix = '/client/';
    private $secondLevelPrefix = '';
    //路由文件存储路径
    const ROUTER_PATH = '/app/Routers/Client';
    //路由标识与路由文件映射关系数组
    private $routerPathMap = [
        'limit_time_activity' => PROJECT_ROOT . self::ROUTER_PATH . '/activity/limitTimeActivityRouter.php',
    ];
    //uri配置文件
    protected $uriConfig = [];

    /**
     * 路由配置规则说明：路由一级为client 路由二级为$routerPathMap的key 路由具体访问的方法有具体的路由文件定义
     * @param $uriPath
     */
    public function __construct($uriPath)
    {
        $uriPathData             = explode('/', $uriPath);
        $this->secondLevelPrefix = $uriPathData[2];
        if (!empty($this->routerPathMap[$this->secondLevelPrefix])) {
            $tmpUriConfig              = require_once $this->routerPathMap[$this->secondLevelPrefix];
            $this->uriConfig[$uriPath] = $tmpUriConfig[str_replace($this->firstLevelPrefix . $this->secondLevelPrefix,
                '', $uriPath)];
        }
    }
}