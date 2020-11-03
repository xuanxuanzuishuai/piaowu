<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/11/03
 * Time: 15:53
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\SimpleLogger;
use App\Services\AIPlayReportService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

/**
 * 每周一（暂定上午九点）向上周有周报数据的学生发送微信推送消息
 * 0 9 * * 1
 */
SimpleLogger::info("push week report wx message start", []);
AIPlayReportService::sendWeekReport();
SimpleLogger::info("push week report wx message end", []);