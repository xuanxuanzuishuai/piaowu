<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/7/29
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
use App\Models\AgentModel;
use App\Services\Queue\AgentTopic;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
$execsStartTime = time();
SimpleLogger::info('agent statics summary data start', []);
//获取一级代理上数据
$parentAgentData = AgentModel::getRecords(['parent_id' => 0], ['id']);
if (empty($parentAgentData)) {
    SimpleLogger::info('agent data empty', []);
    return true;
}
try {
    $topicObj = new AgentTopic();
} catch (\Exception $e) {
    SimpleLogger::error($e->getMessage(), []);
    return false;
}
foreach ($parentAgentData as $val) {
    try {
        $topicObj->staticSummaryData(['agent_id' => $val['id']])->publish(mt_rand(0, 240));
    } catch (\Exception $e) {
        SimpleLogger::error($e->getMessage(), ['queue_data' => $val]);
    }
}
$execEndTime = time();
SimpleLogger::info('agent statics summary data  end', ['exec_time' => $execEndTime - $execsStartTime]);

