<?php

namespace App\Services\Activity\LimitTimeActivity;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssTemplatePosterModel;
use App\Models\LimitTimeActivity\LimitTimeActivityAwardRuleModel;
use App\Models\LimitTimeActivity\LimitTimeActivityAwardRuleVersionModel;
use App\Models\LimitTimeActivity\LimitTimeActivityHtmlConfigModel;
use App\Models\LimitTimeActivity\LimitTimeActivityModel;
use App\Models\OperationActivityModel;
use App\Services\DictService;

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
            'target_use_first_pay_time_start' => $targetUser['target_use_first_pay_time_start'] ?? 0, // 目标用户首次付费时间开始时间
            'target_use_first_pay_time_end'   => $targetUser['target_use_first_pay_time_end'] ?? 0, // 目标用户首次付费时间截止时间
            'invitation_num'                  => $targetUser['invitation_num'] ?? 0, // 邀请人数
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
        foreach ($data['task_list'] as $item) {
            $taskList[] = [
                'activity_id'  => $activityId,
                'award_type'   => $data['award_type'],
                'award_amount' => $item['award_amount'],
                'task_num'     => $item['task_num'],
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
     * 添加限时活动
     * @param $data
     * @param $employeeId
     * @return array
     * @throws RunTimeException
     */
    public static function add($data, $employeeId): array
    {
        SimpleLogger::info("LimitTimeActivityService:add params", [$data]);
        $returnData = [
            'activity_id' => 0,
        ];
        self::checkAllowAdd($data);

        $time = time();
        $operationActivityData = [
            'name'        => $data['activity_name'],
            'app_id'      => $data['app_id'],
            'create_time' => $time,
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
            'create_time'                     => $time,
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
            'create_time'                   => $time,
            'personality_poster_button_img' => $data['personality_poster_button_img'],
            'poster_prompt'                 => !empty($data['poster_prompt']) ? Util::textEncode($data['poster_prompt']) : '',
            'poster_make_button_img'        => $data['poster_make_button_img'],
            'share_poster_prompt'           => !empty($data['share_poster_prompt']) ? Util::textEncode($data['share_poster_prompt']) : '',
            'retention_copy'                => !empty($data['retention_copy']) ? Util::textEncode($data['retention_copy']) : '',
            'award_rule'                    => !empty($data['award_rule']) ? Util::textEncode($data['award_rule']) : '',
            'remark'                        => $data['remark'] ?? '',
            'share_poster'                  => json_encode(self::parseSharePoster($data)),
        ];
        $awardRuleData = self::parseTaskList(0, $data);

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        // 保存活动总表信息
        $activityId = OperationActivityModel::insertRecord($operationActivityData);
        if (empty($activityId)) {
            $db->rollBack();
            SimpleLogger::info("LimitTimeActivityService:add insert operation_activity fail", ['data' => $operationActivityData]);
            throw new RunTimeException(["activity_add_fail"]);
        }
        // 保存奖配置信息
        $activityData['activity_id'] = $activityId;
        $res = LimitTimeActivityModel::insertRecord($activityData);
        if (empty($res)) {
            $db->rollBack();
            SimpleLogger::info("LimitTimeActivityService:add insert week_activity fail", ['data' => $activityData]);
            throw new RunTimeException(["activity_add_fail"]);
        }
        // 保存活动配置页面
        $htmlConfig['activity_id'] = $activityId;
        $res = LimitTimeActivityHtmlConfigModel::insertRecord($htmlConfig);
        if (empty($res)) {
            $db->rollBack();
            SimpleLogger::info("LimitTimeActivityService:add insert week_activity html config fail", ['data' => $htmlConfig]);
            throw new RunTimeException(["activity_add_fail"]);
        }
        // 保存活动奖励规则
        array_walk($awardRuleData, function (&$item) use ($activityId) {
            $item['activity_id'] = $activityId;
        });
        $res = LimitTimeActivityAwardRuleModel::batchInsert($awardRuleData);
        if (empty($res)) {
            $db->rollBack();
            SimpleLogger::info("LimitTimeActivityService:add insert limit time activity html config fail", ['data' => $awardRuleData]);
            throw new RunTimeException(["activity_add_fail"]);
        }
        // 保存活动奖励规则版本
        $awardRuleVersionData = [
            'activity_id' => $activityId,
            'award_info'  => json_encode($awardRuleData),
            'create_time' => $time,
        ];
        $res = LimitTimeActivityAwardRuleVersionModel::insertRecord($awardRuleVersionData);
        if (empty($res)) {
            $db->rollBack();
            SimpleLogger::info("LimitTimeActivityService:add insert limit time activity award rule version config fail", ['data' => $awardRuleVersionData]);
            throw new RunTimeException(["activity_add_fail"]);
        }
        $db->commit();
        $returnData['activity_id'] = $activityId;
        return $returnData;
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
            $returnData['list'][] = self::formatActivityInfo($item);
        }
        return $returnData;
    }

    /**
     * 格式化数据
     * @param $activityInfo
     * @param $extInfo
     * @return mixed
     */
    public static function formatActivityInfo($activityInfo)
    {
        // 处理海报
        // if (!empty($activityInfo['poster']) && is_array($activityInfo['poster'])) {
        //     foreach ($activityInfo['poster'] as $k => $p) {
        //         $activityInfo['poster'][$k]['poster_url'] = AliOSS::replaceCdnDomainForDss($p['poster_path']);
        //         $activityInfo['poster'][$k]['example_url'] = !empty($p['example_path']) ? AliOSS::replaceCdnDomainForDss($p['example_path']) : '';
        //     }
        // }

        $info = self::formatActivityTimeStatus($activityInfo);
        $info['format_start_time'] = date("Y-m-d H:i:s", $activityInfo['start_time']);
        $info['format_end_time'] = date("Y-m-d H:i:s", $activityInfo['end_time']);
        $info['format_create_time'] = date("Y-m-d H:i:s", $activityInfo['create_time']);
        $info['enable_status_zh'] = DictConstants::get(DictConstants::ACTIVITY_ENABLE_STATUS, $activityInfo['enable_status']);
        // $info['banner_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['banner']);
        // $info['share_button_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['share_button_img']);
        // $info['award_detail_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['award_detail_img']);
        // $info['upload_button_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['upload_button_img']);
        // $info['strategy_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['strategy_img']);
        // $info['guide_word'] = Util::textDecode($activityInfo['guide_word']);
        // $info['share_word'] = Util::textDecode($activityInfo['share_word']);
        // $info['personality_poster_button_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['personality_poster_button_img']);
        // $info['poster_prompt'] = Util::textDecode($activityInfo['poster_prompt']);
        // $info['poster_make_button_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['poster_make_button_img']);
        // $info['share_poster_prompt'] = Util::textDecode($activityInfo['share_poster_prompt']);
        // $info['retention_copy'] = Util::textDecode($activityInfo['retention_copy']);
        // $info['delay_day'] = $activityInfo['delay_second']/Util::TIMESTAMP_ONEDAY;
        // $info['format_target_use_first_pay_time_start'] = !empty($activityInfo['target_use_first_pay_time_start']) ? date("Y-m-d H:i:s", $activityInfo['target_use_first_pay_time_start']) : '';
        // $info['format_target_use_first_pay_time_end'] = !empty($activityInfo['target_use_first_pay_time_end']) ? date("Y-m-d H:i:s", $activityInfo['target_use_first_pay_time_end']) : '';
        // $info['has_ab_test_zh'] = !empty($info['has_ab_test']) ? '有' : '无';
        // if (empty($info['remark'])) {
        //     $info['remark'] = $extInfo['remark'] ?? '';
        // }
        // if (!empty($info['award_rule'])) {
        //     $awardRule = $info['award_rule'];
        // } else {
        //     $awardRule = $extInfo['award_rule'] ?? '';
        // }
        // $info['award_rule'] = Util::textDecode($awardRule);
        return $info;
    }

    /**
     * 获取活动开始文字
     * @param $activityInfo
     * @param $time
     * @return array
     */
    public static function formatActivityTimeStatus($activityInfo, $time = 0)
    {
        if (empty($time)) {
            $time = time();
        }
        if ($activityInfo['start_time'] <= $time && $activityInfo['end_time'] >= $time) {
            $activityInfo['activity_time_status'] = OperationActivityModel::TIME_STATUS_ONGOING;
        } elseif ($activityInfo['start_time'] > $time) {
            $activityInfo['activity_time_status'] = OperationActivityModel::TIME_STATUS_PENDING;
        } elseif ($activityInfo['end_time'] < $time) {
            $activityInfo['activity_time_status'] = OperationActivityModel::TIME_STATUS_FINISHED;
        }
        $activityInfo['activity_status_zh'] = DictService::getKeyValue('activity_time_status', $activityInfo['activity_time_status']);
        return $activityInfo;
    }
}