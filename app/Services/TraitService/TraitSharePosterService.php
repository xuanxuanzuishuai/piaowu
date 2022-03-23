<?php
/**
 * author: qingfeng.lian
 * date: 2021/11/24
 * desc: 智能业务线
 */

namespace App\Services\TraitService;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\SimpleLogger;
use App\Models\Erp\ErpEventTaskModel;
use App\Models\OperationActivityModel;
use App\Models\SharePosterModel;
use App\Models\SharePosterPassAwardRuleModel;
use App\Models\WeekActivityModel;
use App\Services\Queue\QueueService;

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
        $activityId2005day = explode(',', DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'activity_id_is_2005day'));
        // 2005天发放的哪些特定活动算新规则
        if ($activityId > $tmpRuleLastActivityId && in_array($activityId, $activityId2005day)) {
            return true;
        }
        // 如果活动类型是即时发奖，那么也属于新规格
        $activityInfo = WeekActivityModel::getRecord(['activity_id' => $activityId]);
        if ($activityInfo['award_prize_type'] == OperationActivityModel::AWARD_PRIZE_TYPE_IN_TIME) {
            return true;
        }
        return false;
    }

    /**
     * 获取审核通过的奖励规则 - 即时发放奖励规则
     * @param $studentId
     * @param $activityId
     * @param int $verifyTime
     * @param array $extData
     * @return array|mixed
     */
    public static function getActivityForthwithSendAwardRule($activityId, int $successSharePosterCount)
    {
        // 根据成功通过审核次数获取应得奖励
        $passAwardInfo = SharePosterPassAwardRuleModel::getRecord(['activity_id' => $activityId, 'success_pass_num' => $successSharePosterCount]);
        if (empty($passAwardInfo)) {
            SimpleLogger::info('addUserAward', ['msg' => 'SharePosterPassAwardRuleModel_not_found', [$activityId, $successSharePosterCount], $passAwardInfo]);
            return [];
        }

        return is_array($passAwardInfo) ? $passAwardInfo : [];
    }

    /**
     * 获取审核通过的奖励规则 - 活动结束后统一发放的规则
     * @param $studentId
     * @param $activityId
     * @param int $verifyTime
     * @param array $extData
     * @return array|mixed
     */
    public static function getActivityOverSendAwardRule($studentId, $activityId)
    {
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
        if (empty($passAwardInfo)) {
            SimpleLogger::info('addUserAward', ['msg' => 'SharePosterPassAwardRuleModel_not_found', [$studentId, $activityId], $passAwardInfo, $successSharePosterCount]);
            return [];
        }

        return is_array($passAwardInfo) ? $passAwardInfo : [];
    }

    /**
     * 获取审核截图审核通过的列表
     * @param $studentId
     * @param $activityId
     * @param int $verifyTime
     * @return array
     */
    public static function getStudentSharePosterPassList($studentId, $activityId, int $verifyTime)
    {
        // 获取用户活动中上传截图成功通过审核的次数
        $where = [
            'activity_id'   => $activityId,
            'student_id'    => $studentId,
            'type'          => SharePosterModel::TYPE_WEEK_UPLOAD,
            'verify_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED,
        ];
        if (!empty($verifyTime)) {
            $where['verify_time[<=]'] = $verifyTime;
        }
        return SharePosterModel::getRecords($where, ['id', 'verify_time', 'points_award_id']);
    }

    /**
     * 给用户发放周周领奖
     * @param $studentInfo
     * @param $activityInfo
     * @param $passAwardInfo
     * @param $status
     * @return bool
     */
    public static function sendStudentWeekActivityAward($studentInfo, $activityInfo, $passAwardInfo, $status, $posterIds = [])
    {
        $studentUUID = $studentInfo['uuid'] ?? 0;
        $studentId   = $studentInfo['id'] ?? 0;
        $activityId  = $activityInfo['activity_id'] ?? 0;
        if (empty($passAwardInfo) || empty($studentId) || empty($studentUUID)) {
            SimpleLogger::info('sendStudentWeekActivityAward', ['msg' => 'params_error', $studentInfo, $activityInfo, $status, $passAwardInfo]);
            return false;
        }
        // 新规则， 海报id一定不能为空
        if (self::checkIsNewRule($activityId) && empty($posterIds)) {
            SimpleLogger::info('sendStudentWeekActivityAward', ['msg' => 'new_rule_is_posterIds_is_empty', $studentInfo, $activityInfo, $status, $posterIds, $passAwardInfo]);
            return false;
        }
        // 获取任务id
        $taskId = DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'week_activity_send_award_task_id');
        // 老规则最后的活动id
        $oldRuleLastActivityId = DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'old_rule_last_activity_id');
        // 发放奖励
        $res = (new Erp())->addEventTaskAward($studentUUID, $taskId, $status, 0, '', [
            'activity_id'               => $activityId,
            'amount'                    => $passAwardInfo['award_amount'] ?? 0,
            'award_to'                  => ErpEventTaskModel::AWARD_TO_BE_REFERRER,
            'passes_num'                => $passAwardInfo['success_pass_num'] ?? 0,
            'old_rule_last_activity_id' => $oldRuleLastActivityId,
            'remark'                    => self::checkIsNewRule($activityId) ? $activityInfo['name'] : '',
        ]);
        SimpleLogger::info('sendStudentWeekActivityAward', ['msg' => 'send_erp_task_award', [$studentInfo, $activityInfo, $passAwardInfo, $status], $res]);
        // 发送消息
        $msgId  = DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'send_award_gold_left_wx_msg_id');
        $msgUrl = DictConstants::get(DictConstants::DSS_JUMP_LINK_CONFIG, 'dss_gold_left_shop_url');
        QueueService::sendUserWxMsg(Constants::SMART_APP_ID, $studentId, $msgId, [
            'replace_params' => [
                'url' => $msgUrl,
            ],
        ]);

        // 更新share_poster表奖励记录表id
        $pointsAwardIds         = $res['data']['points_award_ids'] ?? [];
        $updateSharePosterWhere = [
            'activity_id' => $activityId,
            'student_id'  => $studentId,
        ];
        !empty($posterIds) && $updateSharePosterWhere['id'] = $posterIds;
        SharePosterModel::batchUpdateRecord(['points_award_Id' => implode(',', $pointsAwardIds)], $updateSharePosterWhere);
        return true;
    }
}
