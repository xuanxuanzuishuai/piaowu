<?php
/**
 * 转介绍奖励发放记录表
 */

namespace App\Models;

use App\Libs\Constants;

class ReferralUserAwardModel extends Model
{
    public static $table = 'referral_user_award';

    //奖励发放状态
    const STATUS_DISABLED  = 0; // 不发放
    const STATUS_WAITING   = 1; // 待发放
    const STATUS_REVIEWING = 2; // 审核中
    const STATUS_GIVE      = 3; // 发放成功
    const STATUS_GIVE_ING  = 4; // 发放中/已发放待领取
    const STATUS_GIVE_FAIL = 5; // 发放失败

    // 拒绝发放原因
    const REASON_RETURN_COST = 'return_cost'; // 退费
    const REASON_NO_PLAY     = 'no_play'; // 未练琴
    const REASON_RETURN_DICT = [
        self::REASON_RETURN_COST => '已废除',
        self::REASON_NO_PLAY     => '已废除',
    ];

    // 定义奖励条件字段的代表的意思
    const AWARD_CONDITION = [
        'play_times' => 'play_times',   // 练琴时间单位秒
    ];
    // 其它字段定义
    const OTHER_DATA = [
        'erp_gold_leaf_ids' => 'erp_gold_leaf_ids', // erp_user_event_task_award_gold_leaf表主键
    ];

    /**
     * 增加一条记录
     * @param $data
     * @return int
     */
    public static function addOne($data): int
    {
        $time       = time();
        $insertData = [
            'user_id'          => $data['user_id'] ?? 0,
            'uuid'             => $data['uuid'] ?? '',
            'user_type'        => $data['user_type'] ?? Constants::USER_TYPE_STUDENT,
            'award_rule_id'    => $data['award_rule_id'] ?? 0,
            'award_status'     => $data['award_status'] ?? self::STATUS_WAITING,
            'award_type'       => $data['award_type'] ?? 0,
            'award_amount'     => $data['award_amount'] ?? 0,
            'award_node'       => $data['award_node'] ?? 0,
            'award_time'       => $data['award_time'] ?? 0,
            'award_to'         => $data['award_to'] ?? 0,
            'award_delay'      => $data['award_delay'] ?? 0,
            'reviewer_id'      => $data['reviewer_id'] ?? 0,
            'review_reason'    => $data['review_reason'] ?? '',
            'review_time'      => $data['review_time'] ?? 0,
            'remark'           => $data['remark'] ?? '',
            'finish_task_uuid' => $data['finish_task_uuid'] ?? '',
            'invited_uuid'     => $data['invited_uuid'] ?? '',
            'bill_id'          => $data['bill_id'] ?? '',
            'package_type'     => $data['package_type'] ?? 0,
            'invite_detail_id' => $data['invite_detail_id'] ?? 0,
            'create_time'      => $time,
            'batch_id'         => $data['batch_id'] ?? '',
            'award_condition'  => json_encode($data['award_condition'] ?? []),
            'other_data'       => json_encode($data['other_data'] ?? []),
        ];
        $id         = self::insertRecord($insertData);
        return intval($id);
    }

    /**
     * 状态更新为不发放奖励 - 标记奖励记录状态为已退费
     * @param $awardId
     * @return bool
     */
    public static function disabledAwardByRefund($awardId): bool
    {
        self::updateRecord($awardId, [
            'award_status'  => self::STATUS_DISABLED,
            'reviewer_id'   => EmployeeModel::SYSTEM_EMPLOYEE_ID,
            'review_reason' => self::REASON_RETURN_COST,
            'review_time'   => time()
        ]);
        return true;
    }

    /**
     * 状态更新为发放奖励 - 标记未退费奖励记录状态为发放成功
     * @param $awardId
     * @return bool
     */
    public static function successSendAward($awardId): bool
    {
        self::updateRecord($awardId, [
            'award_status'  => self::STATUS_GIVE,
            'reviewer_id'   => EmployeeModel::SYSTEM_EMPLOYEE_ID,
            'review_reason' => '',
            'review_time'   => time()
        ]);
        return true;
    }

    /**
     * 状态更新为不发放奖励 - 标记奖励记录是未练琴状态
     * @param $awardId
     * @return bool
     */
    public static function disabledAwardByNoPlay($awardId): bool
    {
        self::updateRecord($awardId, [
            'award_status'  => self::STATUS_DISABLED,
            'reviewer_id'   => EmployeeModel::SYSTEM_EMPLOYEE_ID,
            'review_reason' => self::REASON_NO_PLAY,
            'review_time'   => time()
        ]);
        return true;
    }
}
