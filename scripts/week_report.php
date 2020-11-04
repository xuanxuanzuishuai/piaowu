<?php

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Models\AIPlayRecordCHModel;
use App\Services\AIPlayReportService;
use Dotenv\Dotenv;
use App\Libs\Util;
use App\Libs\SimpleLogger;
use Exception;

/**
 * 周报数据
 * 每周一凌晨30启动，跑上周学生数据
 * 30 0 * * 1
 */
$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
try {
    $sm = memory_get_usage();
    $executeStartTime = time();
    SimpleLogger::info("week report start", []);
    //获取本周内练琴记录的学生id
    list($startTime, $endTime, $year, $week) = Util::getDateWeekStartEndTime(strtotime("-1 day"));
    $studentList = array_column(AIPlayRecordCHModel::getBetweenTimePlayStudent($startTime, $endTime), 'student_id');
    //获取学生的周练琴数据
    $studentCount = count($studentList);
    SimpleLogger::info("week report student total count", ['student_count' => $studentCount]);
    if (empty($studentCount)) {
        return true;
    }
    //一次查询500个学生数据
    $forStudentCount = 500;
    $forTimes = ceil($studentCount / $forStudentCount);
    for ($i = 0; $i < $forTimes; $i++) {
        //获取学生周练琴天数/时长数据
        $sidList = array_slice($studentList, $i * $forStudentCount, $forStudentCount);
        $res = AIPlayReportService::makeStudentWeekReport($sidList, $startTime, $endTime, $year, $week);
        if (empty($res)) {
            SimpleLogger::error("week report student insert error", ['student_list' => $sidList]);
        }
    }
} catch (Exception $e) {
    SimpleLogger::error($e->getMessage(), $msgBody ?? []);
    return false;
}
$executeEndTime = time();
$em = memory_get_usage();
$logData = [
    'start_time' => date("Y-m-d H:i:s", $executeStartTime),
    'end_time' => date("Y-m-d H:i:s", $executeEndTime),
    'student_count' => $studentCount,
    'use_memory' => round(($em - $sm) / 1024 / 1024, 1) . 'MB'];
SimpleLogger::info("week report end", $logData);
