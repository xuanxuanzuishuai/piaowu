<?php

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\UserCenter;
use App\Models\CollectionModel;
use App\Models\EmployeeModel;
use App\Models\StudentModel;
use App\Models\MessageRecordModel;
use App\Models\UserWeixinModel;
use App\Services\ErpReferralService;
use App\Services\Queue\PushMessageTopic;
use Dotenv\Dotenv;
use App\Libs\Util;
use App\Libs\SimpleLogger;
use App\Libs\MysqlDB;
use Exception;

/**
 * 给班级活动达标的学生发送《图片上传领返现》活动链接
 * 每天上午8点执行: * 8 * * *  php -f /data/web/dss_crm_prod/scripts/daily_send_share_link.php
 *
 */
$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
$db = MysqlDB::getDB();
// 获取前一天参与活动并在23：59：59结束的班级
$nowTime = time();
$timeData = Util::getStartEndTimestamp(strtotime("-1 day"));
$collectionList = CollectionModel::getRecords(
    [
        'teaching_end_time' => $timeData[1],
        "event_id[!]" => 0,
        "task_id[!]" => 0,
    ],
    [
        'id', 'event_id', 'task_id', 'teaching_start_time', 'teaching_end_time'
    ],
    false);
if (empty($collectionList)) {
    SimpleLogger::info("collection empty ", []);
    return true;
}
//获取班级分配的学生列表
$eventIdList = array_column($collectionList, "event_id");
$collectionIdList = array_column($collectionList, "id");
$studentList = $db->select(
    StudentModel::$table,
    [
        "[>]" . UserWeixinModel::$table => ["id" => "user_id"],
    ],
    [
        StudentModel::$table . ".id",
        StudentModel::$table . ".collection_id",
        UserWeixinModel::$table . ".open_id",
    ],
    [
        StudentModel::$table . ".collection_id" => $collectionIdList,
        UserWeixinModel::$table . ".status" => UserWeixinModel::STATUS_NORMAL,
    ]
);


if (empty($studentList)) {
    SimpleLogger::info("student empty ", []);
    return true;
}
$studentList = array_column($studentList, null, 'id');
$collectionStudentList = [];
foreach ($studentList as $sk => $sv) {
    $collectionStudentList[$sv['collection_id']][] = $sv;
}

//获取学生开班期内练琴数据，按学生和天分组
$studentPlayRecordList = [];
foreach ($collectionList as $ck => $cv) {
    //查看班级是否分配学生
    if (empty($collectionStudentList[$cv['id']])) {
        continue;
    }
    //查询练琴数据
    $sql = "SELECT " . $cv['task_id'] . " as task_id,student_id,SUM(duration) AS sum_duration,FROM_UNIXTIME(end_time, '%Y%m%d') as ed
            FROM
                ai_play_record
            WHERE
                student_id in (" . implode(",", array_column($collectionStudentList[$cv['id']], 'id')) . ")
            AND end_time >= " . $cv['teaching_start_time'] . "
            AND end_time <= " . $cv['teaching_end_time'] . "
            AND duration > 0
            GROUP BY student_id,ed";
    $data = $db->queryAll($sql);
    if (!empty($data)) {
        $studentPlayRecordList = array_merge($studentPlayRecordList, $data);
    }
}
if (empty($studentPlayRecordList)) {
    SimpleLogger::info("student play record list empty ", []);
    return true;
}

//任务达成条件
$tasksList = ErpReferralService::getEventTasksList($eventIdList, 0);
if (empty($tasksList)) {
    SimpleLogger::info("task list empty ", []);
    return true;
}
$taskCondition = array_column($tasksList, null, 'id');

//过滤符合事件活动条件的练琴数据
$upToStandardStudentList = [];
array_walk($studentPlayRecordList, function ($studentPlayValue, $key) use ($taskCondition, &$upToStandardStudentList, $studentList) {
    if ($studentPlayValue['sum_duration'] >= $taskCondition[$studentPlayValue['task_id']]['condition']['per_day_min_play_time']) {
        $upToStandardStudentList[$studentPlayValue['student_id']]['play_standard_count'] += 1;
        $upToStandardStudentList[$studentPlayValue['student_id']]['task_standard_count'] = $taskCondition[$studentPlayValue['task_id']]['condition']['total_qualified_day'];
        $upToStandardStudentList[$studentPlayValue['student_id']]['collection_id'] = $studentList[$studentPlayValue['student_id']]['collection_id'];
        $upToStandardStudentList[$studentPlayValue['student_id']]['open_id'] = $studentList[$studentPlayValue['student_id']]['open_id'];
    }
});
if (empty($upToStandardStudentList)) {
    SimpleLogger::info("event tasks up to standard student empty ", ["run_time" => date("Y-m-d H:i:s"), 'teaching_end_time' => $timeData[1]]);
    return true;
}

//推送消息到消息队列
try {
    $pushTextMessageStudents = $msgBody = [];
    array_walk($upToStandardStudentList, function ($playValue, $studentIdKey) use (&$pushTextMessageStudents, &$msgBody, $nowTime) {
        if ($playValue['play_standard_count'] >= $playValue['task_standard_count']) {
            $msgBody["data"][] = [
                "type" => MessageRecordModel::MSG_TYPE_WEIXIN,
                "activity_id" => $playValue['collection_id'],
                'open_id' => $playValue['open_id'],
                "success_num" => 0,
                "fail_num" => 0,
                "operator_id" => EmployeeModel::SYSTEM_EMPLOYEE_ID,
                "create_time" => $nowTime,
                "activity_type" => MessageRecordModel::ACTIVITY_TYPE_CASH,
            ];
        }
    });
    $msgBody['common_data'] = [
        'app_id' => UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
        'user_type' => UserWeixinModel::USER_TYPE_STUDENT,
    ];
    //批量投递到消息队列,每次50个
    $dataCount = count($pushTextMessageStudents);
    $forCount = 50;
    $topic = new PushMessageTopic();
    for ($i = 0; $i < ceil($dataCount / $forCount); $i++) {
        $queueData = array_slice($msgBody, $i * $forCount, $forCount);
        $topic->pushWX($queueData, $topic::EVENT_PUSH_WX_CASH_SHARE_MESSAGE)->publish();
    }
    SimpleLogger::info("event tasks up to standard student list ", ["student_ids" => $msgBody]);
} catch (Exception $e) {
    SimpleLogger::error($e->getMessage(), $msgBody ?? []);
    return false;
}
return true;