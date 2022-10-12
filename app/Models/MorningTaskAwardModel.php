<?php
/**
 * 清晨用户活动任务奖励信息表
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Models\Erp\ErpUserEventTaskAwardModel;

class MorningTaskAwardModel extends Model
{
    public static $table = 'morning_task_award';

    const MORNING_ACTIVITY_TYPE = 1;    // 清晨5日打卡活动

    //奖励发放状态
    const STATUS_DISABLED  = ErpUserEventTaskAwardModel::STATUS_DISABLED; // 不发放
    const STATUS_WAITING   = ErpUserEventTaskAwardModel::STATUS_WAITING; // 待发放
    const STATUS_REVIEWING = ErpUserEventTaskAwardModel::STATUS_REVIEWING; // 审核中
    const STATUS_GIVE      = ErpUserEventTaskAwardModel::STATUS_GIVE; // 发放成功
    const STATUS_GIVE_ING  = ErpUserEventTaskAwardModel::STATUS_GIVE_ING; // 发放中/已发放待领取
    const STATUS_GIVE_FAIL = ErpUserEventTaskAwardModel::STATUS_GIVE_FAIL; // 发放失败

    /**
     * 获取学生5日打卡奖励信息
     * @param $studentUuid
     * @return array
     */
    public static function getStudentFiveDayAwardList($studentUuid)
    {
        $db = MysqlDB::getDB();
        $sql = "select * from " . self::$table . ' as a' .
            " where a.student_uuid='" . $studentUuid . "'" .
            " and a.activity_type=" . self::MORNING_ACTIVITY_TYPE;
        $list = $db->queryAll($sql);
        return is_array($list) ? $list : [];
    }

    /**
     * @param $taskAwardId
     * @param $reason
     * @param $operatorId
     * @return void
     */
    public static function updateStatusIsDisabled($taskAwardId, $remark, $operatorId)
    {
        $now = time();
        self::updateRecord($taskAwardId, [
            'status'       => self::STATUS_DISABLED,
            'remark'       => $remark,
            'operator_id'  => $operatorId,
            'operate_time' => $now,
            'update_time'  => $now,
        ]);
        MorningReferralDetailModel::batchUpdateRecord(
            [
                'update_time' => $now,
            ],
            [
                'task_award_id' => $taskAwardId,
            ]
        );
    }
}