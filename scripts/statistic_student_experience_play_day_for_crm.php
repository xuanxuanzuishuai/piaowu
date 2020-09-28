<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2020/8/20
 * Time: 10:19 PM
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\PandaCRM;
use App\Libs\SimpleLogger;
use App\Services\StudentService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT,'.env');
$dotenv->load();
$dotenv->overload();

SimpleLogger::info('statistic experience student play day and push to panda-crm script => START', []);

/**
 * 获取用户体验期内练琴天数
 * => Notice: 默认取前一天体验期结束的学生数据
 */
if (!empty($argv[1])) {
    $date = $argv[1];
    $sevenDayDate = date('Y-m-d', strtotime("{$date} -7 day"));
} else {
    $date = date('Y-m-d', strtotime('-1 day'));
    $sevenDayDate = date('Y-m-d', strtotime('-7 day'));
}
SimpleLogger::info('statistic collection end date :', ['date' => $date]);

// 获取统计数据
$data = StudentService::getExperiencePlayDayCount($date);
$sevenDayData = StudentService::getExperience7DayPlayDayCount($sevenDayDate);

//数据不为空，则同步数据到CRM
if($data || $sevenDayData){
    //post 数据到crm
    $crm = new PandaCRM();
    $res = $crm->syncStudentsPlayData(['finishedData' => $data, 'sevenDayData'=>$sevenDayData]);
    SimpleLogger::info('statistic script post data result :', ['result' => $res]);
}

SimpleLogger::info('statistic experience student play day and push to panda-crm script => END', []);
