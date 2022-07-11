<?php

namespace App\Services\Activity\LimitTimeActivity;

use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssTemplatePosterModel;
use App\Models\LimitTimeActivity\LimitTimeActivityAwardRuleModel;
use App\Models\LimitTimeActivity\LimitTimeActivityModel;
use App\Models\OperationActivityModel;
use App\Models\SharePosterModel;
use App\Models\TemplatePosterModel;
use App\Services\Activity\LimitTimeActivity\TraitService\LimitTimeActivityBaseAbstract;

/**
 * 限时有奖活动后台管理功能服务类
 */
class LimitTimeActivityAdminService
{
    /**
     * 检查是否允许添加 - 检查添加必要的参数
     * @param $data
     * @return bool
     * @throws RunTimeException
     */
    public static function checkAllowAdd($data)
    {
        // 个性化海报和通用海报不能同时为空
        if (empty($data['poster']) && empty($data['personality_poster'])) {
            throw new RunTimeException(['poster_or_personality_poster_is_required']);
        }

        // 开始时间不能大于等于结束时间
        $startTime = Util::getDayFirstSecondUnix($data['start_time']);
        $endTime = Util::getDayLastSecondUnix($data['end_time']);
        if ($startTime >= $endTime) {
            throw new RunTimeException(['start_time_eq_end_time']);
        }

        // 检查奖励规则 - 不能为空， 去掉html标签以及emoji表情后不能大于1000个字符
        if (empty($data['award_rule'])) {
            throw new RunTimeException(['award_rule_is_required']);
        }

        if (empty($data['task_list'])) {
            throw new RunTimeException(['task_list_is_required']);
        }
        if (count($data['task_list']) > LimitTimeActivityModel::MAX_TASK_NUM) {
            throw new RunTimeException(['task_list_max_ten']);
        }
        self::checkAwardNum($data['activity_type'], $data['award_type'], $data['task_list']);
        return true;
    }

    /**
     * 检查奖励数量
     * @param $activityType
     * @param $awardType
     * @param $taskList
     * @return bool
     * @throws RunTimeException
     */
    public static function checkAwardNum($activityType, $awardType, $taskList)
    {
        if (empty($taskList)) {
            throw new RunTimeException(['task_list_is_required']);
        }
        $awardMaxData = DictConstants::get(DictConstants::LIMIT_TIME_ACTIVITY_CONFIG, 'limit_time_activity_award_max');
        $awardMaxData = json_decode($awardMaxData, true);
        $errMsg = '';
        $max = $awardMaxData[$activityType][$awardType];
        foreach ($taskList as $item) {
            if ($max > $item['award_amount']) {
                $errMsg = 'award_amount_max';
                break;
            }
        }
        unset($item);
        if (!empty($errMsg)) {
            throw new RunTimeException([$errMsg]);
        }
        return true;
    }

    /**
     * 计算活动发奖时间
     * 发放奖励时间公式：   M(发放奖励时间) = 活动结束时间(天) + 5天 + N天
     * example: 活动结束时间是1号23:59:59， 发放奖励时间是 5+1天 ， 则  M= 1+5+1 = 7, 得出是在7号12点发放奖励
     * @param $activityEndTime
     * @param $awardPrizeType
     * @param int $delayDay
     * @return array
     */
    public static function getActivityDelaySendAwardTime($activityEndTime, $awardPrizeType, $delayDay = 0)
    {
        $data = [
            'delay_second'    => 0,
            'send_award_time' => 0,
        ];
        if ($awardPrizeType == OperationActivityModel::AWARD_PRIZE_TYPE_DELAY) {
            $data['delay_second'] = !empty($delayDay) ? $delayDay * Util::TIMESTAMP_ONEDAY : 0;
            $sendAwardBaseDelaySecond = DictConstants::get(DictConstants::LIMIT_TIME_ACTIVITY_CONFIG, 'send_award_base_delay_second');
            $data['send_award_time'] = Util::getStartEndTimestamp($activityEndTime)[0] + $sendAwardBaseDelaySecond + $data['delay_second'];
        }
        return $data;
    }

    /**
     * 解析目标用户属性标签
     * @param $data
     * @return array
     */
    public static function parseTargetUser($data)
    {
        $targetUser = $data['target_user'] ?? [];
        return [
            'target_user_first_pay_time_start' => $targetUser['target_user_first_pay_time_start'] ?? 0, // 目标用户首次付费时间开始时间
            'target_user_first_pay_time_end'   => $targetUser['target_user_first_pay_time_end'] ?? 0, // 目标用户首次付费时间截止时间
            'invitation_num'                   => $targetUser['invitation_num'] ?? 0, // 邀请人数
        ];
    }

    /**
     * 解析任务列表
     * @param $activityId
     * @param $data
     * @return array
     */
    public static function parseTaskList($activityId, $data)
    {
        $taskList = [];
        $taskNum = 0;
        foreach ($data['task_list'] as $item) {
            if ($data['activity_type'] == OperationActivityModel::ACTIVITY_TYPE_FULL_ATTENDANCE) {
                $taskNum = $item['task_num'];
            } else {
                $taskNum += 1;
            }
            $taskList[] = [
                'activity_id'  => $activityId,
                'award_type'   => $data['award_type'],
                'award_amount' => $item['award_amount'],
                'task_num'     => $taskNum,
                'create_time'  => time(),
            ];
        }
        unset($item);
        return $taskList;
    }

    /**
     * 解析海报
     * @param $data
     * @return array
     */
    public static function parseSharePoster($data)
    {
        $sharePoster[DssTemplatePosterModel::STANDARD_POSTER] = $data['poster'] ?? [];
        $sharePoster[DssTemplatePosterModel::INDIVIDUALITY_POSTER] = $data['personality_poster'] ?? [];
        return $sharePoster;
    }

    /**
     * 获取限时领奖活动列表和总数
     * @param $params
     * @param $page
     * @param $limit
     * @return array
     */
    public static function searchList($params, $page, $limit)
    {
        $limitOffset = [($page - 1) * $limit, $limit];
        if (!empty($params['start_time_s'])) {
            $params['start_time_s'] = strtotime($params['start_time_s']);
        }
        if (!empty($params['start_time_e'])) {
            $params['start_time_e'] = strtotime($params['start_time_e']);
        }
        list($list, $total) = LimitTimeActivityModel::searchList($params, $limitOffset);
        $returnData = ['total_count' => $total, 'list' => []];
        foreach ($list as $item) {
            $returnData['list'][] = LimitTimeActivityBaseAbstract::formatActivityInfo($item);
        }
        return $returnData;
    }

    /**
     * 保存限时活动 （根据是否有活动id，决定是更新还是新增）
     * @param $data
     * @param $employeeId
     * @return array
     * @throws RunTimeException
     */
    public static function save($data, $employeeId): array
    {
        SimpleLogger::info("LimitTimeActivityService:save params", [$data]);
        $returnData = [
            'activity_id' => 0,
        ];
        self::checkAllowAdd($data);

        $time = time();
        $operationActivityData = [
            'name'   => $data['activity_name'],
            'app_id' => $data['app_id'],
        ];
        $targetUser = self::parseTargetUser($data);
        // 计算发奖时间
        $activityEndTime = Util::getDayLastSecondUnix($data['end_time']);
        $delaySendAwardTimeData = self::getActivityDelaySendAwardTime($activityEndTime, $data['award_prize_type'], $data['delay_day']);
        $activityData = [
            'app_id'                          => $data['app_id'],
            'activity_name'                   => $data['activity_name'],
            'activity_id'                     => 0,
            'activity_type'                   => $data['activity_type'],
            'start_time'                      => Util::getDayFirstSecondUnix($data['start_time']),
            'end_time'                        => Util::getDayLastSecondUnix($data['end_time']),
            'enable_status'                   => OperationActivityModel::ENABLE_STATUS_OFF,
            'operator_id'                     => $employeeId,
            'target_user_type'                => intval($data['target_user_type']),
            'activity_country_code'           => intval($data['activity_country_code']),    // 0代表全部
            'target_user'                     => json_encode($targetUser),
            'target_use_first_pay_time_start' => $targetUser['target_use_first_pay_time_start'],
            'target_use_first_pay_time_end'   => $targetUser['target_use_first_pay_time_end'],
            'award_prize_type'                => $data['award_prize_type'],
            'delay_second'                    => $delaySendAwardTimeData['delay_second'],
            'send_award_time'                 => $delaySendAwardTimeData['send_award_time'],
        ];
        $htmlConfig = [
            'activity_id'                   => 0,
            'guide_word'                    => !empty($data['guide_word']) ? Util::textEncode($data['guide_word']) : '',
            'share_word'                    => !empty($data['share_word']) ? Util::textEncode($data['share_word']) : '',
            'banner'                        => $data['banner'] ?? '',
            'share_button_img'              => $data['share_button_img'] ?? '',
            'award_detail_img'              => $data['award_detail_img'] ?? '',
            'upload_button_img'             => $data['upload_button_img'] ?? '',
            'strategy_img'                  => $data['strategy_img'] ?? '',
            'operator_id'                   => $employeeId,
            'personality_poster_button_img' => $data['personality_poster_button_img'],
            'poster_prompt'                 => !empty($data['poster_prompt']) ? Util::textEncode($data['poster_prompt']) : '',
            'poster_make_button_img'        => $data['poster_make_button_img'],
            'share_poster_prompt'           => !empty($data['share_poster_prompt']) ? Util::textEncode($data['share_poster_prompt']) : '',
            'retention_copy'                => !empty($data['retention_copy']) ? Util::textEncode($data['retention_copy']) : '',
            'award_rule'                    => !empty($data['award_rule']) ? Util::textEncode($data['award_rule']) : '',
            'remark'                        => $data['remark'] ?? '',
            'share_poster'                  => json_encode(self::parseSharePoster($data)),
            'first_poster_type_order'       => $data['first_poster_type_order'] ?? TemplatePosterModel::INDIVIDUALITY_POSTER,
        ];
        $awardRuleData = self::parseTaskList(0, $data);

        if (!empty($data['activity_id']) && is_numeric($data['activity_id']) && intval($data['activity_id']) > 0) {
            /** 更新 */
            $activityId = intval($data['activity_id']);
            // 活动是否存在
            $activityInfo = LimitTimeActivityModel::getRecord(['activity_id' => $activityId]);
            if (empty($activityInfo)) {
                throw new RunTimeException(['record_not_found']);
            }
            // 删掉创建时的不要参数
            unset($operationActivityData['create_time'], $activityData['create_time'], $htmlConfig['create_time']);
            // 如果是非待启用状态 - 某些字段不能编辑
            // 一旦启用，不论是不是禁用了，都不能编辑奖励信息以及活动开始时间
            if ($activityInfo['enable_status'] != OperationActivityModel::ENABLE_STATUS_OFF) {
                $activityData = [
                    'activity_name' => $activityData['activity_name'],
                    'end_time'      => $activityData['end_time'],
                    'enable_status' => $activityData['enable_status'],
                    'update_time'   => $activityData['update_time'],
                    'operator_id'   => $activityData['operator_id'],
                ];
            }
            LimitTimeActivityModel::modify($activityInfo, $operationActivityData, $activityData, $htmlConfig, $awardRuleData, $time);
            $returnData['activity_id'] = $activityId;
        } else {
            /** 新增 */
            $returnData['activity_id'] = LimitTimeActivityModel::add($operationActivityData, $activityData, $htmlConfig, $awardRuleData, $time);
        }
        return $returnData;
    }

    /**
     * 获取活动详情
     * @param $activityId
     * @return mixed
     * @throws RunTimeException
     */
    public static function getActivityDetail($activityId)
    {
        $activityInfo = LimitTimeActivityModel::getActivityDetail($activityId);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        $activityInfo = LimitTimeActivityBaseAbstract::formatActivityInfo($activityInfo);
        // 获取活动海报
        $sharePosterList = LimitTimeActivityBaseAbstract::getSharePosterList(json_decode($activityInfo['share_poster'], true));
        $activityInfo['poster'] = $sharePosterList[TemplatePosterModel::STANDARD_POSTER] ?? [];
        $activityInfo['personality_poster'] = $sharePosterList[TemplatePosterModel::INDIVIDUALITY_POSTER] ?? [];
        // 获取奖励
        $activityInfo['task_list'] = LimitTimeActivityAwardRuleModel::getActivityAwardRule($activityId);
        return $activityInfo;
    }

    /**
     * 更改活动的状态
     * @param $activityId
     * @param $enableStatus
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function editEnableStatus($activityId, $enableStatus, $employeeId)
    {
        SimpleLogger::info("LimitTimeActivityService:editEnableStatus params", [$activityId, $enableStatus, $employeeId]);
        if (!in_array($enableStatus, [OperationActivityModel::ENABLE_STATUS_ON, OperationActivityModel::ENABLE_STATUS_DISABLE])) {
            throw new RunTimeException(['enable_status_invalid']);
        }
        $activityInfo = LimitTimeActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        if ($activityInfo['enable_status'] == $enableStatus) {
            return true;
        }
        if ($enableStatus == OperationActivityModel::ENABLE_STATUS_ON) {
            // 如果是启用活动 - 校验活动是否允许启动
            LimitTimeActivityBaseAbstract::getRangeTimeEnableActivity($activityInfo['app_id'], $activityInfo['start_time'], $activityInfo['end_time'], $activityInfo['activity_country_code']);
        }
        // 修改启用状态
        $res = LimitTimeActivityModel::updateRecord($activityInfo['id'], ['enable_status' => $enableStatus, 'operator_id' => $employeeId, 'update_time' => time()]);
        if (is_null($res)) {
            throw new RunTimeException(['update_failure']);
        }
        return true;
    }

    /**
     * 获取审核截图搜索条件中下拉框的活动列表
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function getFilterAfterActivityList($params, $page, $count)
    {
        $returnData = [
            'total_count' => 0,
            'list'        => [],
        ];
        $searchWhere = [
            'enable_status' => [OperationActivityModel::ENABLE_STATUS_ON, OperationActivityModel::ENABLE_STATUS_OFF],
            'app_id' => $params['app_id'],
        ];
        // 根据名称模糊(不支持分词) 、 活动id
        $searchName = $params['search_name'] ?? '';
        if (!empty($searchName)) {
            if (is_numeric($searchName)) {
                $searchWhere['OR'] = [
                    'AND #one' => ['a.activity_id' => intval($searchName)],
                    'AND #two' => ['a.activity_name[~]' => trim($searchName)],
                ];
            } else {
                $searchWhere['activity_name'] = $searchName;
            }
        }
        // 分享截图的审核状态
        if (!empty($params['share_poster_verify_status']) && $params['share_poster_verify_status'] == SharePosterModel::VERIFY_STATUS_WAIT) {
            $searchWhere['share_poster_verify_status'] = $params['share_poster_verify_status'];
        }
        // 分页
        (!empty($page) && !empty($count)) && $searchWhere['LIMIT'] = [($page - 1) * $count, $count];
        list($returnData['total_count'], $returnData['list']) = LimitTimeActivityModel::getFilterAfterActivityList($searchWhere);
        return $returnData;
    }
}