<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/1/7
 * Time: 3:19 PM
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Services\CreditService;
use App\Services\HalloweenService;
use App\Services\MedalService;
use App\Services\NoviceActivityService;
use App\Services\TermSprintService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT,'.env');
$dotenv->load();
$dotenv->overload();
//创建今天的任务还是明天的任务
if (!empty($argv[1])) {
    $date = date('Y-m-d');
} else {
    $date = date('Y-m-d', strtotime('+1 day'));
}
CreditService::createEveryDayTask($date);
MedalService::createEveryDayTask($date);
TermSprintService::createEveryDayTask($date);
HalloweenService::setEventTaskCache($date);
NoviceActivityService::createEventTaskCache($date);