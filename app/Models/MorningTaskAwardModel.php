<?php
/**
 * 清晨用户活动任务奖励信息表
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Models\Erp\ErpUserEventTaskAwardModel;
use Medoo\Medoo;

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

    /**
     * 搜索列表
     * @param $params
     * @param $page
     * @param $count
     * @param $order
     * @param $fields
     * @return array
     */
    public static function searchList($params, $page, $count, $order = ['award.id' => 'DESC'], $fields = [])
    {
        $where = [];
        !empty($params['student_uuid']) && $where['award.student_uuid'] = $params['student_uuid'];
        !empty($params['task_num']) && $where['award.task_num'] = $params['task_num'];
        !empty($params['award_node']) && $where['award.award_node'] = $params['award_node'];
        !empty($params['status']) && $where['award.status'] = $params['status'];
        !empty($params['operate_time_start']) && $where['award.operate_time[>=]'] = $params['operate_time_start'];
        !empty($params['operate_time_end']) && $where['award.operate_time[<=]'] = $params['operate_time_end'];
        !empty($params['create_time_start']) && $where['award.create_time[>=]'] = $params['create_time_start'];
        !empty($params['create_time_end']) && $where['award.create_time[<=]'] = $params['create_time_end'];
        !empty($params['activity_type']) && $where['award.activity_type'] = $params['activity_type'];
        !empty($params['award_type']) && $where['award.award_type'] = $params['award_type'];
        !empty($params['operator_name']) && $where['e.name'] = $params['operator_name'];
        $db = MysqlDB::getDB();
        $totalCount = $db->count(
            self::$table . ' (award)',
            [
                '[>]' . EmployeeModel::$table . ' (e)' => ['award.operator_id' => 'id'],
            ],
            ['award.id'],
            $where
        );
        if (empty($totalCount)) {
            return [0, []];
        }

        $where['LIMIT'] = [($page - 1) * $count, $count];
        $where['ORDER'] = $order;
        $where['GROUP'] = ['wacd.task_award_id'];
        $list = $db->select(
            self::$table . ' (award)',
            [
                '[>]' . EmployeeModel::$table . ' (e)'                      => ['award.operator_id' => 'id'],
                '[>]' . MorningWechatAwardCashDealModel::$table . ' (wacd)' => ['award.id' => 'task_award_id'],
            ],
            array_merge([
                "award.id",
                "award.student_uuid",
                'award.status',
                'award.task_num',
                'award.award_node',
                'award.award_amount',
                'award.operator_id',
                'award.operate_time',
                'award.remark',
                'award.create_time',
                'e.name (operator_name)',
                'result_codes' => Medoo::raw("ifnull(group_concat(wacd.id),'')"),
            ], $fields),
            $where
        );
        return [$totalCount, is_array($list) ? $list : []];
    }
}