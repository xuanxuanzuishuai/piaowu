<?php
/**
 * author: qingfeng.lian
 * date: 2021/11/24
 * desc: 智能业务线
 */

namespace App\Services\TraitService;

use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use App\Models\SharePosterModel;
use App\Models\SharePosterPassAwardRuleModel;

trait TraitSharePosterService
{
    /**
     * 检查活动是否使用新活动规则
     * 新老规则说明：2022-02-25
     * 老规则是多少天后脚本统一发放审核通过的累计奖励；
     * 新规则是审核通过后立即发放奖励；
     * @param $activityId
     * @return bool
     */
    public static function checkIsNewRule($activityId)
    {
        $tmpRuleLastActivityId = DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'tmp_rule_last_activity_id');
        if ($activityId > $tmpRuleLastActivityId) {
            return true;
        }
        return false;
    }

    /**
     * 获取审核通过的奖励规则
     * @param $studentId
     * @param $activityId
     * @param int $time
     * @return array|mixed
     */
    public static function getStudentAwardRule($studentId, $activityId, $time = 0)
    {
        if (self::checkIsNewRule($activityId)) {
            /** 新规则 */
            $time = !empty($time) ? $time : time();
            // 获取用户活动中上传截图成功通过审核的次数
            $successSharePosterCount = SharePosterModel::getCount([
                'activity_id'     => $activityId,
                'student_id'      => $studentId,
                'type'            => SharePosterModel::TYPE_WEEK_UPLOAD,
                'verify_status'   => SharePosterModel::VERIFY_STATUS_QUALIFIED,
                'verify_time[<=]' => $time,
            ]);
            // 根据成功通过审核次数获取应得奖励
            $passAwardInfo = SharePosterPassAwardRuleModel::getRecord(['activity_id' => $activityId, 'success_pass_num' => $successSharePosterCount]);
        } else {
            /** 老规则 */
            // 获取用户活动中上传截图成功通过审核的次数
            $successSharePosterCount = SharePosterModel::getCount([
                'activity_id'   => $activityId,
                'student_id'    => $studentId,
                'type'          => SharePosterModel::TYPE_WEEK_UPLOAD,
                'verify_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED
            ]);
            // 根据成功通过审核次数获取应得奖励
            $passAwardInfo = SharePosterPassAwardRuleModel::getRecord(['activity_id' => $activityId, 'success_pass_num' => $successSharePosterCount]);

        }
        if (empty($passAwardInfo)) {
            SimpleLogger::info('addUserAward', ['msg' => 'SharePosterPassAwardRuleModel_not_found', [$studentId, $activityId, $time], $passAwardInfo, $activityId, $successSharePosterCount]);
            return [];
        }
        return $passAwardInfo;
    }
}
