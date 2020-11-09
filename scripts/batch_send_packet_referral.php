<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/09
 * Time: 10:10
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

use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Models\EmployeeModel;
use App\Models\GiftCodeModel;
use App\Models\PackageExtModel;
use App\Models\ReferralActivityModel;
use App\Models\SharePosterModel;
use App\Models\StudentModel;
use App\Models\WeChatAwardCashDealModel;
use App\Services\ErpReferralService;
use Dotenv\Dotenv;

class BatchSend
{
    public $userList20           = [];  // 20元红包用户列表：
    public $userList10           = [];  // 10元红包用户列表：
    public $studentIDUUIDDict    = [];  // student id->uuid
    public $updateTaskFlag       = true;// 是否完成任务；否则需要填写userAwardID,格式:[student_uuid => award_id]
    public $userAwardID          = [];
    public $usleep               = 200; // 发奖间隔时间
    public $taskID10             = 0;   // 10元红包task ID
    public $taskID20             = 0;   // 20元红包task ID
    private $startTime           = 0;   // 开始时间
    private $endTime             = 0;   // 结束时间
    private $activityIDs         = [];  // 所有时间段内的活动
    private $totalActivityNumber = 0;   // 时间段内活动总数
    private $db                  = null;

    public function __construct($s, $e)
    {

        $dotenv = new Dotenv(PROJECT_ROOT, '.env');
        $dotenv->load();
        $dotenv->overload();

        if (is_null($this->db)) {
            $this->db = MysqlDB::getDB();
        }
        if (!empty($s) && is_numeric($s)) {
            $this->startTime = $s;
        }
        if (!empty($e) && is_numeric($e)) {
            $this->endTime = $e;
        }
    }

    public function getActivityData()
    {
        if (empty($this->startTime) || empty($this->endTime)) {
            return;
        }
        $sql = "
        SELECT 
            id 
        FROM 
            ".ReferralActivityModel::$table." 
        WHERE 
            `status` =  ".ReferralActivityModel::STATUS_ENABLE."
            AND start_time >= ".$this->startTime." 
            AND end_time <= ".$this->endTime;

        $activityIDs = $this->db->queryAll($sql);
        $this->activityIDs = array_column($activityIDs, 'id');
        $this->totalActivityNumber = count($this->activityIDs);
    }

    public function getSharePosterData()
    {
        if (empty($this->activityIDs)) {
            return;
        }
        $sql = "
        SELECT 
            `student_id`,
            `activity_id` 
        FROM 
            ".SharePosterModel::$table." 
        WHERE 
            `status` = ".SharePosterModel::STATUS_QUALIFIED."
            AND `type` = ".SharePosterModel::TYPE_UPLOAD_IMG."
            AND activity_id IN (".implode(',', $this->activityIDs).");";

        $sharePosterData = $this->db->queryAll($sql);

        $studentIDs = array_column($sharePosterData, 'student_id');
        $userAttendList = [];
        foreach ($sharePosterData as $key => $value) {
            $userAttendList[$value['student_id']][$value['activity_id']] = $value['activity_id'];
        }
        foreach ($userAttendList as $studentID => $attendData) {
            if (count($attendData) == $this->totalActivityNumber) {
                $this->userList20[] = $studentID;
            }
        }
        $this->generate10List($studentIDs);
    }

    public function generate10List($studentIDs)
    {
        if (empty($studentIDs)) {
            return;
        }
        $sql = "SELECT `uuid`,`id` FROM ".StudentModel::$table." WHERE id IN (".implode(',', $studentIDs).");";
        $res = $this->db->queryAll($sql);
        $this->studentIDUUIDDict = array_combine(array_column($res, 'id'), array_column($res, 'uuid'));
        // 查询所有用户成为付费正式课时间点
        $packageIdArr = array_column(PackageExtModel::getPackages(['package_type' => PackageExtModel::PACKAGE_TYPE_NORMAL, 'app_id' => PackageExtModel::APP_AI]), 'package_id');
        $sql = "
        SELECT * FROM 
        (
            SELECT 
                buyer,
                create_time, 
                ROW_NUMBER() over 
                (
                    PARTITION BY buyer
                    ORDER BY create_time ASC
                ) AS row_num 
            FROM 
                ".GiftCodeModel::$table." 
            WHERE 
                buyer IN (".implode(',', $studentIDs).") 
                AND `bill_package_id` IN (".implode(',', $packageIdArr).")
        ) AS t WHERE row_num = 1 AND create_time >=". $this->startTime." AND create_time <= ".$this->endTime;
        $records = $this->db->queryAll($sql);
        foreach ($records as $key => $value) {
            if ($value['create_time'] >= $this->startTime && $value['create_time'] <= $this->endTime) {
                $this->userList10[] = $value['buyer'];
            }
        }
    }

    public function sendPacket()
    {
        $taskID = $this->taskID10;
        foreach ($this->userList20 as $userID) {
            $this->sendUserPacket($userID, $taskID);
        }
        
        $taskID = $this->taskID20;
        foreach ($this->userList10 as $userID) {
            $this->sendUserPacket($userID, $taskID);
        }
    }

    public function sendUserPacket($userID, $taskID)
    {
        usleep($this->usleep);
        SimpleLogger::info("Send Packet:", [$userID, $taskID]);
        if (!isset($this->studentIDUUIDDict[$userID]) || empty($taskID)) {
            return;
        }
        if ($this->updateTaskFlag) {
            $awardInfo = $this->updateTask($this->studentIDUUIDDict[$userID], $taskID);
            $awardID = $awardInfo['data']['user_award_ids'][0];
        } else {
            $awardID = $this->userAwardID[$this->studentIDUUIDDict[$userID]] ?? '';
        }
        if (!empty($awardID)) {
            $this->updateAward($awardID);
        } else {
            SimpleLogger::info("Empty award id", [$awardInfo, $userID]);
            return;
        }
    }


    public function updateTask($uuid, $taskID)
    {
        if (empty($uuid) || empty($taskID)) {
            return;
        }
        // 完成任务
        return (new Erp())->updateTask($uuid, $taskID, ErpReferralService::EVENT_TASK_STATUS_COMPLETE);
    }

    public function updateAward($awardID)
    {
        if (empty($awardID)) {
            return;
        }
        try {
            // 发奖
            $res = ErpReferralService::updateAward(
                $awardID,
                ErpReferralService::AWARD_STATUS_GIVEN,
                EmployeeModel::SYSTEM_EMPLOYEE_ID,
                '',
                WeChatAwardCashDealModel::REISSUE_PIC_WORD
            );
            SimpleLogger::info("Send Result", [$res]);
        } catch (RunTimeException $e) {
            SimpleLogger::error("Send Error", [$e->getMessage()]);
        }
    }
}

if (isset($argv[1]) && strtotime($argv[1]) !== false) {
    $startTime = strtotime($argv[1].'-01 00:00:00');
} else {
    $startTime = strtotime(date('Y-m-01 00:00:00'));
}
$endTime = strtotime("+1 month", $startTime);
$task = new BatchSend($startTime, $endTime);
$task->getActivityData();
$task->getSharePosterData();
$task->sendPacket();
// echo "Send 20yuan List:".PHP_EOL;
// if (!empty($task->userList20)) {
//     print_r(MysqlDB::getDB()->queryAll(
//         "SELECT mobile FROM `student` WHERE `id` IN (".implode(',', $task->userList20).")"
//     ));
// }
// echo "Send 10yuan List:".PHP_EOL;
// if (!empty($task->userList10)) {
//     print_r(MysqlDB::getDB()->queryAll(
//         "SELECT mobile FROM `student` WHERE `id` IN (".implode(',', $task->userList10).")"
//     ));
// }
