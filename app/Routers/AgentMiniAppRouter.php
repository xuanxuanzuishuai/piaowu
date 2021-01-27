<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2021/1/21
 * Time: 12:47
 */

namespace App\Routers;

use App\Controllers\Agent\Auth;
use App\Middleware\AgentMiniAppOpenIdMiddleware;
use App\Middleware\AgentMiniAppMiddleware;

class AgentMiniAppRouter extends RouterBase
{
    protected $logFilename = 'operation_agent_mini.log';
    public $middleWares = [AgentMiniAppMiddleware::class];
    protected $uriConfig = [
        '/agent/user/login'            => ['method' => ['get'], 'call' => Auth::class . ':login', 'middles' => [AgentMiniAppOpenIdMiddleware::class]],
        '/agent/user/logout'           => ['method' => ['get'], 'call' => Auth::class . ':logout'],
        '/agent/user/application'      => ['method' => ['post'], 'call' => Auth::class . ':application', 'middles' => [AgentMiniAppOpenIdMiddleware::class]],
        '/agent/user/login_code'       => ['method' => ['get'], 'call' => Auth::class . ':loginSmsCode', 'middles' => []],
        '/agent/user/application_code' => ['method' => ['get'], 'call' => Auth::class . ':applicationCode', 'middles' => []],
    ];
}