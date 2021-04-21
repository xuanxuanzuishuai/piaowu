<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/3/29
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

use App\Libs\DictConstants;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Models\AgentUserModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\Erp\ErpUserEventTaskModel;
use App\Models\StudentInviteModel;
use Dotenv\Dotenv;

/**
 * 转介绍数据清洗脚本，只执行一次（2021.5.8）
 * 执行命令格式：sudo -u www-data php  /data/web/operation_backend/scripts/wash_referral_data.php 开始位置 结束位置 操作类型
 */
$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
SimpleLogger::info('referral wash start', []);
$startMemory = memory_get_usage();
//数据的开始位置
$startId = (int)$argv[1];
if (empty($startId)) {
    SimpleLogger::error("start id don`t empty", []);
    return false;
}
//数据的结束位置
$endId = (int)$argv[2];
if (empty($endId)) {
    SimpleLogger::error("end id don`t empty", []);
    return false;
}
if ($endId <= $startId) {
    SimpleLogger::error("start glt end", []);
    return false;
}

//操作类型:1注册 2体验卡 3年卡
$actionType = (int)$argv[3];
if (empty($actionType)) {
    SimpleLogger::error("action type don`t empty", []);
    return false;
}
//数据总量
//每次查询的数据条数
$batchSelectCount = 500;
$taskIds = '';
if ($actionType == 1) {
    //1.注册
    $stage = AgentUserModel::STAGE_REGISTER;
} elseif ($actionType == 2) {
    //体验卡任务ID
    $taskIds = DictConstants::get(DictConstants::NODE_RELATE_TASK, 2);
    $stage = AgentUserModel::STAGE_TRIAL;
} elseif ($actionType == 3) {
    //年卡任务ID
    $taskIds = DictConstants::get(DictConstants::NODE_RELATE_TASK, 3);
    $stage = AgentUserModel::STAGE_FORMAL;
} else {
    SimpleLogger::error("action type error", []);
    return false;
}
//获取数据总量
$dataCount = StudentInviteModel::getCount(['id[>=]' => $startId, 'id[<=]' => $endId, 'referee_type' => StudentInviteModel::REFEREE_TYPE_STUDENT]);
if (empty($dataCount)) {
    SimpleLogger::error("data count empty", []);
    return false;
}

if ($actionType == 1) {
    //1.注册
    register($startId, $endId, $dataCount, $batchSelectCount, $stage);
} else {
    if (empty($taskIds)) {
        SimpleLogger::error("tasks empty", []);
        return false;
    }
    //体验卡任务ID
    trailAndNormal($startId, $endId, $dataCount, $batchSelectCount, $taskIds, $stage);
}

//注册
function register($startId, $endId, $dataCount, $batchSelectCount, $stage)
{
    $db = MysqlDB::getDB();
    for ($i = 1; $i <= ceil($dataCount / $batchSelectCount); $i++) {
        //获取数据列表
        $data = StudentInviteModel::getRecords(
            [
                'id[>=]' => $startId,
                'id[<=]' => $endId,
                'referee_type' => StudentInviteModel::REFEREE_TYPE_STUDENT,
                'LIMIT' => [($i - 1) * $batchSelectCount, $batchSelectCount]
            ]);
        $statisticsSql = 'INSERT INTO `student_referral_student_statistics`
                              (`student_id`, `last_stage`, `referee_id`, `referee_employee_id`, `activity_id`, `create_time`)
                             VALUES';
        $detailSql = 'INSERT INTO `student_referral_student_detail`
                              (`student_id`, `stage`, `create_time`) 
                      VALUES';
        foreach ($data as $k => $v) {
            $statisticsSql .= '( ' . $v['student_id'] . ',' . $stage . ' ,' . $v['referee_id'] . ', ' . $v['referee_employee_id'] . ', ' . $v['activity_id'] . ', ' . $v['create_time'] . '),';

            $detailSql .= ' (' . $v['student_id'] . ', ' . $stage . ', ' . $v['create_time'] . '),';
        }
        //批量写入数据库
        $db->queryAll(trim($statisticsSql, ','));
        $db->queryAll(trim($detailSql, ','));
        unset($data);
    }
}


//体验卡&&年卡
function trailAndNormal($startId, $endId, $dataCount, $batchSelectCount, $taskIds, $stage)
{
    $db = MysqlDB::getDB();
    $dssStudentTable = DssStudentModel::getTableNameWithDb();
    $erpStudentTable = ErpStudentModel::getTableNameWithDb();
    $erpUserEventTaskTable = ErpUserEventTaskModel::getTableNameWithDb();
    for ($i = 1; $i <= ceil($dataCount / $batchSelectCount); $i++) {
        //获取数据列表
        $data = StudentInviteModel::getRecords(
            [
                'id[>=]' => $startId,
                'id[<=]' => $endId,
                'referee_type' => StudentInviteModel::REFEREE_TYPE_STUDENT,
                'LIMIT' => [($i - 1) * $batchSelectCount, $batchSelectCount]
            ],
            [
                'student_id[Int]',
                'referee_id',
                'referee_type',
                'create_time',
                'referee_employee_id',
                'activity_id',
            ]);
        //获取任务完成数据
        $sql = 'SELECT ds.id,eut.create_time FROM ' . $dssStudentTable . ' AS ds INNER JOIN ' . $erpStudentTable . ' AS es ON ds.uuid=es.uuid 
                INNER JOIN ' . $erpUserEventTaskTable . ' AS eut ON es.id=eut.user_id AND event_task_id in(' . $taskIds . ') AND eut.status=2 AND app_id=8 AND user_type=1 
                WHERE ds.id IN(' . implode(',', array_column($data, 'student_id')) . ') GROUP BY ds.id';
        $erpStudentData = $db->queryAll($sql);
        if (empty($erpStudentData)) {
            continue;
        }
        $statisticsSql = 'UPDATE `student_referral_student_statistics` 
                              SET `last_stage` = ' . $stage . ' WHERE `student_id` in (';
        $detailSql = 'INSERT INTO `student_referral_student_detail`
                              (`student_id`, `stage`, `create_time`) 
                      VALUES';
        foreach ($erpStudentData as $k => $v) {
            $statisticsSql .= (int)$v['id'] . ',';

            $detailSql .= ' (' . $v['id'] . ', ' . $stage . ', ' . $v['create_time'] . '),';
        }
        //批量写入数据库
        $db->queryAll(trim($statisticsSql, ',') . ')');
        $db->queryAll(trim($detailSql, ','));
        unset($data);
        unset($erpStudentData);
    }
}

$endMemory = memory_get_usage();
SimpleLogger::info('referral wash end', ['memory' => (($endMemory - $startMemory) / 1024 / 1024).'M']);
