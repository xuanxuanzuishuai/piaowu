<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/8/3
 * Time: 4:43 PM
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Services\AIPlayReportService;
use App\Services\WeChatService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT,'.env');
$dotenv->load();
$dotenv->overload();


$dateTime = strtotime('today');

SimpleLogger::info('send_daily_play_report START', ['$dateTime' => $dateTime]);

AIPlayReportService::sendDailyReport($dateTime);