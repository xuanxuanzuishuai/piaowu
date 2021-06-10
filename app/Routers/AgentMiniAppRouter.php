<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2021/1/21
 * Time: 12:47
 */

namespace App\Routers;

use App\Controllers\Agent\Agent;
use App\Controllers\Agent\Auth;
use App\Controllers\Agent\Order;
use App\Controllers\Agent\User;
use App\Middleware\AgentMiniAppOpenIdMiddleware;
use App\Middleware\AgentMiniAppMiddleware;

class AgentMiniAppRouter extends RouterBase
{
    protected $logFilename = 'operation_agent_mini.log';
    public $middleWares = [AgentMiniAppMiddleware::class];
    protected $uriConfig = [
        '/agent/user/login'            => ['method' => ['get'], 'call' => Auth::class . ':login', 'middles' => [AgentMiniAppOpenIdMiddleware::class]],
        '/agent/user/logout'           => ['method' => ['get'], 'call' => Auth::class . ':logout'],
        '/agent/user/application'      => ['method' => ['post'], 'call' => Auth::class . ':application', 'middles' => []],
        '/agent/user/login_code'       => ['method' => ['get'], 'call' => Auth::class . ':loginSmsCode', 'middles' => []],
        '/agent/user/application_code' => ['method' => ['get'], 'call' => Auth::class . ':applicationCode', 'middles' => []],
        // 代理的绑定用户列表
        '/agent/user/bind_list' => ['method' => ['get'], 'call' => User::class . ':bindList'],
        // 代理的推广订单列表
        '/agent/order/list' => ['method' => ['get'], 'call' => Order::class . ':list'],
        // 首页
        '/agent/user/index' => ['method' => ['get'], 'call' => Agent::class . ':miniAppIndex'],
        // 素材
        '/agent/config/get' => ['method' => ['get'], 'call' => Agent::class . ':getConfig'],
        '/agent/config/country_code' => ['method' => ['get'], 'call' => Agent::class . ':countryCode', 'middles' => []],
        // 我的下级代理：
        '/agent/sec_agent/list'     => ['method' => ['get'],  'call' => Agent::class . ':secAgentList'],
        '/agent/sec_agent/detail'   => ['method' => ['get'],  'call' => Agent::class . ':secAgentDetail'],
        '/agent/sec_agent/parent'   => ['method' => ['get'],  'call' => Agent::class . ':secAgentParent'],
        '/agent/sec_agent/add'      => ['method' => ['post'], 'call' => Agent::class . ':secAgentAdd'],
        '/agent/sec_agent/update'   => ['method' => ['post'], 'call' => Agent::class . ':secAgentUpdate'],
        '/agent/sec_agent/freeze'   => ['method' => ['post'], 'call' => Agent::class . ':secAgentFreeze'],
        '/agent/sec_agent/unfreeze' => ['method' => ['post'], 'call' => Agent::class . ':secAgentUnfreeze'],
        // 售卖课包
        '/agent/package/list' => ['method' => ['get'], 'call' => Agent::class . ':packageList'],
        '/agent/package/detail' => ['method' => ['get'], 'call' => Agent::class . ':packageDetail'],
        '/agent/package/share' => ['method' => ['get'], 'call' => Agent::class . ':packageShareInfo'],
        // 机构信息
        '/agent/org/cover_data' => ['method' => ['get'], 'call' => Agent::class . ':orgCoverData'],
        '/agent/org/opn_list' => ['method' => ['get'], 'call' => Agent::class . ':orgOpnList'],

    ];
}