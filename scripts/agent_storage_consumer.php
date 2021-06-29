<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/5/28
 * Time: 15:38
 */

namespace App;

date_default_timezone_set('PRC');
set_time_limit(0);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\SimpleLogger;
use App\Models\AgentOrganizationModel;
use App\Services\AgentStorageService;
use Dotenv\Dotenv;

/**
 * 代理商预存订单年卡消费定时任务:每五分钟执行一次
 */
$dotEnv = new Dotenv(PROJECT_ROOT, '.env');
$dotEnv->load();
$dotEnv->overload();
SimpleLogger::info('agent storage consumer start', []);
$startTime = time();
//查询存在可以消费预存订单数据的一级代理商
$agentData = AgentOrganizationModel::getRecords(['quantity[>]' => 0], ['agent_id']);
if (empty($agentData)) {
    SimpleLogger::info('agent storage empty', []);
    return false;
}
foreach ($agentData as $av) {
    AgentStorageService::agentStorageConsumer($av['agent_id']);
}
$endTime = time();
SimpleLogger::info('agent storage consumer end', ['duration' => ($startTime - $endTime) . 's']);
