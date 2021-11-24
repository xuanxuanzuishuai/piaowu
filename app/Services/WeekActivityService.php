<?php


namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\NewSMS;
use App\Libs\RealDictConstants;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\ActivityExtModel;
use App\Models\ActivityPosterModel;
use App\Models\CHModel\AprViewStudentModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\MessagePushRulesModel;
use App\Models\MessageRecordModel;
use App\Models\OperationActivityModel;
use App\Models\SharePosterDesignateUuidModel;
use App\Models\SharePosterModel;
use App\Models\SharePosterPassAwardRuleModel;
use App\Models\SharePosterTaskListModel;
use App\Models\TemplatePosterModel;
use App\Models\WeekActivityModel;
use App\Services\Queue\QueueService;
use I18N\Lang;

class WeekActivityService
{

    const WEEK_ACTIVITY_TYPE = 1; //周周活动类型
    const MONTH_ACTIVITY_TYPE = 2; //月月活动类型

    /**
     * 添加周周领奖活动
     * @param $data
     * @param $employeeId
     * @return array
     * @throws RunTimeException
     */
    public static function add($data, $employeeId): array
    {
        $returnData = [
            'activity_id' => 0,
            'no_exists_uuid' => [],
            'activity_having_uuid' => [],
        ];
        $checkAllowAdd = self::checkAllowAdd($data, self::WEEK_ACTIVITY_TYPE);
        if (!empty($checkAllowAdd)) {
            throw new RunTimeException([$checkAllowAdd]);
        }
        // 检查uuid是否正确
        $errUuid = UserService::checkDssStudentUuidExists($data['designate_uuid'] ?? [], 0);
        if (!empty($errUuid['no_exists_uuid'])) {
            $returnData['no_exists_uuid'] = $errUuid['no_exists_uuid'];
            return $returnData;
        }
        $time = time();
        $activityData = [
            'name' => $data['name'] ?? '',
            'app_id' => Constants::SMART_APP_ID,
            'create_time' => $time,
        ];
        /**
         * 计算发奖时间
         * 发放奖励时间公式：   M(发放奖励时间) = 活动结束时间(天) + 5天 + N天
         * example: 活动结束时间是1号23:59:59， 发放奖励时间是 5+1天 ， 则  M= 1+5+1 = 7, 得出是在7号12点发放奖励
         */
        $delaySecond = !empty($data['delay_day']) ? $data['delay_day'] * Util::TIMESTAMP_ONEDAY : 0;
        $sendAwardBaseDelaySecond = DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'send_award_base_delay_second');
        $delayDay = (intval($sendAwardBaseDelaySecond) + $delaySecond) / Util::TIMESTAMP_ONEDAY;
        $activityEndTime = Util::getDayLastSecondUnix($data['end_time']);
        $weekActivityData = [
            'name' => $activityData['name'],
            'activity_id' => 0,
            'event_id' => $data['event_id'] ?? 0,
            'guide_word' => !empty($data['guide_word']) ? Util::textEncode($data['guide_word']) : '',
            'share_word' => !empty($data['share_word']) ? Util::textEncode($data['share_word']) : '',
            'start_time' => Util::getDayFirstSecondUnix($data['start_time']),
            'end_time' => Util::getDayLastSecondUnix($data['end_time']),
            'enable_status' => OperationActivityModel::ENABLE_STATUS_OFF,
            'banner' => $data['banner'] ?? '',
            'share_button_img' => $data['share_button_img'] ?? '',
            'award_detail_img' => $data['award_detail_img'] ?? '',
            'upload_button_img' => $data['upload_button_img'] ?? '',
            'strategy_img' => $data['strategy_img'] ?? '',
            'operator_id' => $employeeId,
            'create_time' => $time,
            'personality_poster_button_img' => $data['personality_poster_button_img'],
            'poster_prompt' => !empty($data['poster_prompt']) ? Util::textEncode($data['poster_prompt']) : '',
            'poster_make_button_img' => $data['poster_make_button_img'],
            'share_poster_prompt' => !empty($data['share_poster_prompt']) ? Util::textEncode($data['share_poster_prompt']) : '',
            'retention_copy' => !empty($data['retention_copy']) ? Util::textEncode($data['retention_copy']) : '',
            'poster_order' => $data['poster_order'],
            'target_user_type' => intval($data['target_user_type']),
            'target_use_first_pay_time_start' => !empty($data['target_use_first_pay_time_start']) ? strtotime($data['target_use_first_pay_time_start']) : 0,
            'target_use_first_pay_time_end' => !empty($data['target_use_first_pay_time_end']) ? strtotime($data['target_use_first_pay_time_end']) : 0,
            'delay_second' => $delaySecond,
            'send_award_time' => strtotime(date("Y-m-d", $activityEndTime) . " +$delayDay day"),
            'priority_level' => $data['priority_level'] ?? 0,
        ];

        $activityExtData = [
            'activity_id' => 0,
            'award_rule' => !empty($data['award_rule']) ? Util::textEncode($data['award_rule']) : '',
            'remark' => $data['remark'] ?? ''
        ];

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        // 保存活动总表信息
        $activityId = OperationActivityModel::insertRecord($activityData);
        if (empty($activityId)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add insert operation_activity fail", ['data' => $activityData]);
            throw new RunTimeException(["add week activity fail"]);
        }
        // 保存周周领奖配置信息
        $weekActivityData['activity_id'] = $activityId;
        $weekActivityId = WeekActivityModel::insertRecord($weekActivityData);
        if (empty($weekActivityId)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add insert week_activity fail", ['data' => $weekActivityData]);
            throw new RunTimeException(["add week activity fail"]);
        }
        // 保存周周领奖扩展信息
        $activityExtData['activity_id'] = $activityId;
        $activityExtId = ActivityExtModel::insertRecord($activityExtData);
        if (empty($activityExtId)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add insert activity_ext fail", ['data' => $activityExtData]);
            throw new RunTimeException(["add week activity fail"]);
        }
        // 保存海报关联关系
        $posterArray = array_merge($data['personality_poster'] ?? [], $data['poster'] ?? []);
        $activityPosterRes = ActivityPosterModel::batchAddActivityPoster($activityId, $posterArray);
        if (empty($activityPosterRes)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add batch insert activity_poster fail", ['data' => $data]);
            throw new RunTimeException(["add week activity fail"]);
        }
        // 保存分享任务
        $ruleRes = SharePosterTaskListModel::batchInsertActivityTask($activityId, $data['task_list'], $time);
        if (empty($ruleRes)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add batch insert share_poster_task_list fail", ['data' => $data]);
            throw new RunTimeException(["add_week_activity_task_fail"]);
        }
        // 保存分享任务奖励规则
        $ruleRes = SharePosterPassAwardRuleModel::batchInsertPassAwardRule($activityId, $data['task_list'], $time);
        if (empty($ruleRes)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add batch insert share_poster_pass_award fail", ['data' => $data]);
            throw new RunTimeException(["add_week_activity_pass_award_fail"]);
        }
        // 保存uuid
        if (!empty($data['designate_uuid'])) {
            $saveUuidRes = SharePosterDesignateUuidModel::batchInsertUuid($activityId, array_unique($data['designate_uuid']), $employeeId, $time);
            if (empty($saveUuidRes)) {
                $db->rollBack();
                SimpleLogger::info("WeekActivityService:add batch insert real_share_poster_designate_uuid fail", ['data' => $data]);
                throw new RunTimeException(["add_week_activity_designate_uuid_fail"]);
            }
        }
        $db->commit();
        $returnData['activity_id'] = $activityId;
        return $returnData;
    }

    /**
     * 检查是否允许添加 - 检查添加必要的参数
     * @param $data
     * @return bool
     */
    public static function checkAllowAdd($data, $type = self::MONTH_ACTIVITY_TYPE)
    {
        // 海报不能为空
        if (empty($data['poster']) && $type == self::MONTH_ACTIVITY_TYPE) {
            return 'poster_is_required';
        }

        // 开始时间不能大于等于结束时间
        $startTime = Util::getDayFirstSecondUnix($data['start_time']);
        $endTime = Util::getDayLastSecondUnix($data['end_time']);
        if ($startTime >= $endTime) {
            return 'start_time_eq_end_time';
        }

        // 检查奖励规则 - 不能为空， 去掉html标签以及emoji表情后不能大于1000个字符
        if (empty($data['award_rule'])) {
            return 'award_rule_is_required';
        }
        // 部分真人付费有效用户 需要检查首次付费时间
        $targetUserType = $data['target_user_type'] ?? 0;
        $startFirstPayTime = !empty($data['target_use_first_pay_time_start']) ?  strtotime($data['target_use_first_pay_time_start']) : 0;
        $endFirstPayTime = !empty($data['target_use_first_pay_time_end']) ?  strtotime($data['target_use_first_pay_time_end']) : 0;
        if ($targetUserType == WeekActivityModel::TARGET_USER_PART) {
            if ($startFirstPayTime <= 0 || $startFirstPayTime >= $endFirstPayTime) {
                return 'first_start_time_eq_end_time';
            }
        }
        // 分享任务不能为空
        if (empty($data['task_list'])) {
            return 'task_list_is_required';
        }
        return '';
    }

    /**
     * 获取周周领奖活动列表和总数
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
        list($list, $total) = WeekActivityModel::searchList($params, $limitOffset);

        // 获取备注
        $activityIds = array_column($list, 'activity_id');
        if (!empty($activityIds)) {
            $activityExtList = ActivityExtModel::getRecords(['activity_id' => $activityIds]);
            $activityExtArr = array_column($activityExtList, null, 'activity_id');
        }

        $returnData = ['total_count' => $total, 'list' => []];
        foreach ($list as $item) {
            $extInfo = $activityExtArr[$item['activity_id']] ?? [];
            $returnData['list'][] = self::formatActivityInfo($item, $extInfo);
        }
        return $returnData;
    }

    /**
     * 格式化数据
     * @param $activityInfo
     * @param $extInfo
     * @return mixed
     */
    public static function formatActivityInfo($activityInfo, $extInfo)
    {
        // 处理海报
        if (!empty($activityInfo['poster']) && is_array($activityInfo['poster'])) {
            foreach ($activityInfo['poster'] as $k => $p) {
                $activityInfo['poster'][$k]['poster_url'] = AliOSS::replaceCdnDomainForDss($p['poster_path']);
                $activityInfo['poster'][$k]['example_url'] = !empty($p['example_path']) ? AliOSS::replaceCdnDomainForDss($p['example_path']) : '';
            }
        }

        $info = self::formatActivityTimeStatus($activityInfo);
        $info['format_start_time'] = date("Y-m-d H:i:s", $activityInfo['start_time']);
        $info['format_end_time'] = date("Y-m-d H:i:s", $activityInfo['end_time']);
        $info['format_create_time'] = date("Y-m-d H:i:s", $activityInfo['create_time']);
        $info['enable_status_zh'] = DictConstants::get(DictConstants::ACTIVITY_ENABLE_STATUS, $activityInfo['enable_status']);
        $info['banner_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['banner']);
        $info['share_button_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['share_button_img']);
        $info['award_detail_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['award_detail_img']);
        $info['upload_button_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['upload_button_img']);
        $info['strategy_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['strategy_img']);
        $info['guide_word'] = Util::textDecode($activityInfo['guide_word']);
        $info['share_word'] = Util::textDecode($activityInfo['share_word']);
        $info['personality_poster_button_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['personality_poster_button_img']);
        $info['poster_prompt'] = Util::textDecode($activityInfo['poster_prompt']);
        $info['poster_make_button_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['poster_make_button_img']);
        $info['share_poster_prompt'] = Util::textDecode($activityInfo['share_poster_prompt']);
        $info['retention_copy'] = Util::textDecode($activityInfo['retention_copy']);
        $info['delay_day'] = $activityInfo['delay_second']/Util::TIMESTAMP_ONEDAY;
        $info['format_target_use_first_pay_time_start'] = !empty($activityInfo['target_use_first_pay_time_start']) ? date("Y-m-d H:i:s", $activityInfo['target_use_first_pay_time_start']) : '';
        $info['format_target_use_first_pay_time_end'] = !empty($activityInfo['target_use_first_pay_time_end']) ? date("Y-m-d H:i:s", $activityInfo['target_use_first_pay_time_end']) : '';
        if (empty($info['remark'])) {
            $info['remark'] = $extInfo['remark'] ?? '';
        }
        if (!empty($info['award_rule'])) {
            $awardRule = $info['award_rule'];
        } else {
            $awardRule = $extInfo['award_rule'] ?? '';
        }
        $info['award_rule'] = Util::textDecode($awardRule);
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

    /**
     * 获取海报详情
     * @param $activityId
     * @return mixed
     * @throws RunTimeException
     */
    public static function getDetailById($activityId)
    {
        $activityInfo = WeekActivityModel::getDetailByActivityId($activityId);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        // 获取活动海报
        $activityInfo['poster'] = PosterService::getActivityPosterList($activityInfo);
        $activityInfo = self::formatActivityInfo($activityInfo, []);
        $poster = [];
        $personality_poster = [];
        foreach ($activityInfo['poster'] as $val) {
            if ($val['type'] == TemplatePosterModel::STANDARD_POSTER) {
                $poster[] = $val;
            } elseif ($val['type'] == TemplatePosterModel::INDIVIDUALITY_POSTER) {
                $personality_poster[] = $val;
            }
        }
        $activityInfo['poster'] = $poster;
        $activityInfo['personality_poster'] = $personality_poster;
        // 获取活动对应的任务
        $activityInfo['task_list'] = SharePosterTaskListModel::getRecords(['activity_id' => $activityId, 'ORDER' => ['task_num' => 'ASC']]);
        // 获取奖励
        $activityInfo['pass_award_rule_list'] = SharePosterPassAwardRuleModel::getRecords(['activity_id' => $activityId, 'ORDER' => ['success_pass_num' => 'ASC']]);
        foreach ($activityInfo['task_list'] as $index => &$item) {
            $passAwardRuleInfo = $activityInfo['pass_award_rule_list'][$index] ?? [];
            if (empty($passAwardRuleInfo)) {
                continue;
            }
            // 前端展示 - 兼容字段
            $passAwardRuleInfo['task_award'] = $passAwardRuleInfo['award_amount'];
            $item = array_merge($item, $passAwardRuleInfo);
        }
        unset($index, $item);

        // 获取uuid
        $activityInfo['designate_uuid'] = SharePosterDesignateUuidModel::getUUIDByActivityId($activityId);
        return $activityInfo;
    }

    /**
     * 修改周周领奖活动
     * @param $data
     * @param $employeeId
     * @return array
     * @throws RunTimeException
     */
    public static function edit($data, $employeeId): array
    {
        $returnData = [
            'activity_id' => 0,
            'no_exists_uuid' => [],
            'activity_having_uuid' => [],
        ];
        $checkAllowAdd = self::checkAllowAdd($data, self::WEEK_ACTIVITY_TYPE);
        if (!empty($checkAllowAdd)) {
            throw new RunTimeException([$checkAllowAdd]);
        }
        // 检查是否存在
        if (empty($data['activity_id'])) {
            throw new RunTimeException(['record_not_found']);
        }
        $activityId = intval($data['activity_id']);
        $weekActivityInfo = WeekActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($weekActivityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        // 活动是待启用状态，检查导入的uuid是否正确
        if ($weekActivityInfo['enable_status'] == OperationActivityModel::ENABLE_STATUS_OFF) {
            $errUuid = UserService::checkStudentUuidExists(Constants::REAL_APP_ID, $data['designate_uuid'] ?? [], $activityId);
            if (!empty($errUuid['no_exists_uuid']) || !empty($errUuid['activity_having_uuid'])) {
                $returnData['no_exists_uuid'] = $errUuid['no_exists_uuid'];
                $returnData['activity_having_uuid'] = $errUuid['activity_having_uuid'];
                return $returnData;
            }
        }
        // 判断海报是否有变化，没有变化不操作
        $posterArray = array_merge($data['personality_poster'] ?? [], $data['poster'] ?? []);
        $isDelPoster = ActivityPosterModel::diffPosterChange($activityId, $posterArray);
        // 开始处理更新数据
        $time = time();
        // 检查是否有海报
        $activityData = [
            'name' => $data['name'] ?? '',
            'update_time' => $time,
        ];
        $delaySecond = !empty($data['delay_day']) ? $data['delay_day'] * Util::TIMESTAMP_ONEDAY : 0;
        $sendAwardBaseDelaySecond = DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'send_award_base_delay_second');
        $weekActivityData = [
            'activity_id' => $activityId,
            'name' => $activityData['name'],
            'guide_word' => !empty($data['guide_word']) ? Util::textEncode($data['guide_word']) : '',
            'share_word' => !empty($data['share_word']) ? Util::textEncode($data['share_word']) : '',
            'start_time' => Util::getDayFirstSecondUnix($data['start_time']),
            'end_time' => Util::getDayLastSecondUnix($data['end_time']),
            'banner' => $data['banner'] ?? '',
            'share_button_img' => $data['share_button_img'] ?? '',
            'award_detail_img' => $data['award_detail_img'] ?? '',
            'upload_button_img' => $data['upload_button_img'] ?? '',
            'strategy_img' => $data['strategy_img'] ?? '',
            'operator_id' => $employeeId,
            'update_time' => $time,
            'personality_poster_button_img' => $data['personality_poster_button_img'],
            'poster_prompt' => !empty($data['poster_prompt']) ? Util::textEncode($data['poster_prompt']) : '',
            'poster_make_button_img' => $data['poster_make_button_img'],
            'share_poster_prompt' => !empty($data['share_poster_prompt']) ? Util::textEncode($data['share_poster_prompt']) : '',
            'retention_copy' => !empty($data['retention_copy']) ? Util::textEncode($data['retention_copy']) : '',
            'poster_order' => $data['poster_order'],
            'target_user_type' => intval($data['target_user_type']),
            'target_use_first_pay_time_start' => !empty($data['target_use_first_pay_time_start']) ? strtotime($data['target_use_first_pay_time_start']) : 0,
            'target_use_first_pay_time_end' => !empty($data['target_use_first_pay_time_end']) ? strtotime($data['target_use_first_pay_time_end']) : 0,
            'delay_second' => $delaySecond,
            'send_award_time' => $delaySecond + $time + intval($sendAwardBaseDelaySecond),
            'priority_level' => $data['priority_level'] ?? 0,
        ];

        $activityExtData = [
            'award_rule' => !empty($data['award_rule']) ? Util::textEncode($data['award_rule']) : '',
            'remark' => $data['remark'] ?? ''
        ];

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        // 更新活动总表信息
        $res = OperationActivityModel::batchUpdateRecord($activityData, ['id' => $activityId]);
        if (is_null($res)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add update operation_activity fail", ['data' => $activityData, 'activity_id' => $activityId]);
            throw new RunTimeException(["update week activity fail"]);
        }
        // 更新周周领奖配置信息
        $res = WeekActivityModel::batchUpdateRecord($weekActivityData, ['activity_id' => $activityId]);
        if (is_null($res)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add update week_activity fail", ['data' => $weekActivityData, 'activity_id' => $activityId]);
            throw new RunTimeException(["update week activity fail"]);
        }
        // 更新周周领奖扩展信息
        $activityExtData['activity_id'] = $activityId;
        $res = ActivityExtModel::batchUpdateRecord($activityExtData, ['activity_id' => $activityId]);
        if (is_null($res)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add update activity_ext fail", ['data' => $weekActivityData, 'activity_id' => $activityId]);
            throw new RunTimeException(["update week activity fail"]);
        }
        // 当海报有变化时删除原有的海报
        if ($isDelPoster) {
            // 删除海报关联关系
            $res = ActivityPosterModel::batchUpdateRecord(['is_del' => ActivityPosterModel::IS_DEL_TRUE], ['activity_id' => $activityId]);
            if (is_null($res)) {
                $db->rollBack();
                SimpleLogger::info("WeekActivityService:add is del activity_poster fail", ['data' => $weekActivityData, 'activity_id' => $activityId]);
                throw new RunTimeException(["update week activity fail"]);
            }
            // 写入新的活动与海报的关系
            $activityPosterRes = ActivityPosterModel::batchAddActivityPoster($activityId, $posterArray);
            if (empty($activityPosterRes)) {
                $db->rollBack();
                SimpleLogger::info("WeekActivityService:add batch insert activity_poster fail", ['data' => $weekActivityData, 'activity_id' => $activityId]);
                throw new RunTimeException(["add week activity fail"]);
            }
        }
        // 待启用的状态可以额外编辑 分享任务和uuid 等
        if ($weekActivityInfo['enable_status'] == OperationActivityModel::ENABLE_STATUS_OFF) {
            //更新分享任务
            $ruleRes = SharePosterTaskListModel::batchUpdateActivityTask($activityId, $data['task_list'], $time);
            if (empty($ruleRes)) {
                $db->rollBack();
                SimpleLogger::info("WeekActivityService:add batch insert share_poster_task_rule fail", ['data' => $data]);
                throw new RunTimeException(["add week activity fail"]);
            }
            // 更新分享通过奖励数据
            $ruleRes = SharePosterPassAwardRuleModel::batchUpdatePassAwardRule($activityId, $data['task_list'], $time);
            if (empty($ruleRes)) {
                $db->rollBack();
                SimpleLogger::info("WeekActivityService:add batch insert share_poster_task_rule fail", ['data' => $data]);
                throw new RunTimeException(["add week activity fail"]);
            }
            // 更新uuid - 先删除， 后新增
            if (!empty($data['designate_uuid'])) {
                $delRes = SharePosterDesignateUuidModel::delDesignateUUID($activityId, [], $employeeId);
                if (empty($delRes)) {
                    $db->rollBack();
                    SimpleLogger::info("RealSharePosterDesignateUuidModel:delDesignateUUID batch del share_poster_designate_uuid fail", ['data' => $data]);
                    throw new RunTimeException(["add week activity fail"]);
                }
                $saveUuidRes = SharePosterDesignateUuidModel::batchInsertUuid($activityId, array_unique($data['designate_uuid']), $employeeId, $time);
                if (empty($saveUuidRes)) {
                    $db->rollBack();
                    SimpleLogger::info("WeekActivityService:add batch insert share_poster_designate_uuid fail", ['data' => $data]);
                    throw new RunTimeException(["add week activity fail"]);
                }
            }
        }
        $db->commit();

        $returnData['activity_id'] = $activityId;
        return $returnData;
    }

    /**
     * 更改周周有奖的状态
     * @param $activityId
     * @param $enableStatus
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function editEnableStatus($activityId, $enableStatus, $employeeId)
    {
        if (!in_array($enableStatus, [OperationActivityModel::ENABLE_STATUS_OFF, OperationActivityModel::ENABLE_STATUS_ON, OperationActivityModel::ENABLE_STATUS_DISABLE])) {
            throw new RunTimeException(['enable_status_invalid']);
        }
        $activityInfo = WeekActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        if ($activityInfo['enable_status'] == $enableStatus) {
            return true;
        }
        // 如果是启用 检查时间是否冲突
        if ($enableStatus == OperationActivityModel::ENABLE_STATUS_ON) {
            $startActivity = WeekActivityModel::checkTimeConflict($activityInfo['start_time'], $activityInfo['end_time'], $activityInfo['event_id']);
            if (!empty($startActivity)) {
                throw new RunTimeException(['activity_time_conflict_id', '', '', array_column($startActivity, 'activity_id')]);
            }
        }

        // 修改启用状态
        $res = WeekActivityModel::updateRecord($activityInfo['id'], ['enable_status' => $enableStatus, 'operator_id' => $employeeId, 'update_time' => time()]);
        if (is_null($res)) {
            throw new RunTimeException(['update_failure']);
        }

        // 删除缓存
        ActivityService::delActivityCache(
            $activityId,
            [
                OperationActivityModel::KEY_CURRENT_ACTIVE,
            ],
            [
                OperationActivityModel::KEY_CURRENT_ACTIVE . '_poster_type' => TemplatePosterModel::STANDARD_POSTER,   // 周周领奖 - 标准海报
            ]
        );

        return true;
    }

    /**
     * 活动处于“进行中”且“已启用”状态
     * @param $activityInfo
     * @return bool
     */
    public static function checkActivityStatus($activityInfo)
    {
        $now = time();
        if ($activityInfo['enable_status'] != OperationActivityModel::ENABLE_STATUS_ON
            || $activityInfo['start_time'] > $now
            || $activityInfo['end_time'] < $now) {
            return false;
        }
        return true;
    }
    /**
     * 发送参与活动短信
     * @param $activityId
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function sendActivitySMS($activityId, $employeeId)
    {
        $activityInfo = WeekActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        // 若活动处于“进行中”且“已启用”状态，则短信提醒的【发送】功能可用
        $check = self::checkActivityStatus($activityInfo);
        if (!$check) {
            throw new RunTimeException(['send_sms_activity_status_error']);
        }
        $startTime = date('m月d日', $activityInfo['start_time']);
        // 当前阶段为付费正式课且未参加当前活动的学员
        $students = self::getPaidAndNotAttendStudents($activityId);
        $sms = new NewSMS(DictConstants::get(DictConstants::SERVICE, 'sms_host'));
        $sign = CommonServiceForApp::SIGN_STUDENT_APP;
        $i = 0;
        $mobiles = [];
        $successNum = 0;

        foreach ($students as $student) {
            $i ++;
            if ($student['country_code'] == NewSMS::DEFAULT_COUNTRY_CODE) {
                $mobiles[] = $student['mobile'];
            }

            if ($i >= 200) {
                $result = $sms->sendAttendActSMS($mobiles, $sign, $startTime);
                if ($result) {
                    $successNum += count($mobiles);
                }
                $i = 0;
                $mobiles = [];
            }
        }

        // 剩余数量小于200
        if (!empty($mobiles)) {
            $result = $sms->sendAttendActSMS($mobiles, $sign, $startTime);
            if ($result) {
                $successNum += count($mobiles);
            }
        }

        // 发短信记录
        $failNum = count($students) - $successNum;
        MessageRecordService::add(MessageRecordModel::MSG_TYPE_SMS, $activityId, $successNum, $failNum, $employeeId, time(), MessageRecordModel::ACTIVITY_TYPE_AWARD);

        return true;
    }

    /**
     * 查询符合条件的微信用户，发送活动通知
     * @param $activityId
     * @param $employeeId
     * @param $guideWord
     * @param $shareWord
     * @param $posterUrl
     * @return bool
     * @throws RunTimeException
     */
    public static function sendWeixinMessage($activityId, $employeeId, $guideWord, $shareWord, $posterUrl)
    {
        if (empty($guideWord) && empty($shareWord) && empty($posterUrl)) {
            throw new RunTimeException(['no_send_message']);
        }
        $activityInfo = WeekActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        // 若活动处于“进行中”且“已启用”状态，则客服消息提醒的【发送】功能可用
        $check = self::checkActivityStatus($activityInfo);
        if (!$check) {
            throw new RunTimeException(['send_weixin_activity_status_error']);
        }

        // 当前阶段为付费正式课且未参加当前活动的学员
        $boundUsers = self::getPaidAndNotAttendStudents($activityId);
        if (empty($boundUsers)) {
            throw new RunTimeException(['no_students']);
        }

        // 写入发送信息的log ,然后把logId放入到队列
        $manualData = [
            'push_type' => MessagePushRulesModel::PUSH_TYPE_CUSTOMER,
            'content_1' => $guideWord,
            'content_2' => $shareWord,
            'image' => $posterUrl,
        ];
        $logId = MessageService::saveSendLog($manualData);
        $uuidArr = [];
        $userTotal = count($boundUsers);
        $lastNum = $userTotal - 1;
        for ($i = 0; $i < $userTotal; $i++) {
            $uuidArr[] = $boundUsers[$i]['uuid'];
            if (count($uuidArr) >= 1000 || $i == $lastNum) {
                QueueService::manualBatchPushRuleWxByUuid(
                    $logId,
                    $uuidArr,
                    $employeeId
                );
                $uuidArr = [];
            }
        }
        return true;
    }


    /**
     * 当前阶段为付费正式课且未参加当前活动的学员手机号和uuid
     * @param $activityId
     * @return array
     */
    public static function getPaidAndNotAttendStudents($activityId)
    {
        // 获取所有参数活动的学生id
        $inActivityStudent = SharePosterModel::getRecords(['activity_id' => $activityId], ['student_id']);
        $inActivityStudentId = [];
        if (!empty($inActivityStudent)) {
            $inActivityStudentId = array_column($inActivityStudent, 'student_id', 'student_id');
        }
        $noJoinActivityStudent = [];
        // 获取所有年卡用户 - 每次处理10w条
        $lastId = 0;
        while (true) {
            $tmpWhere = ['has_review_course' => DssStudentModel::REVIEW_COURSE_1980, 'status' => DssStudentModel::STATUS_NORMAL, 'ORDER' => ['id' => 'ASC'], 'LIMIT' => 50000, 'id[>]' => $lastId];
            $studentList = DssStudentModel::getRecords($tmpWhere, ['id', 'mobile', 'country_code', 'uuid']);
            // 没有数据跳出
            if (empty($studentList)) {
                break;
            }
            foreach ($studentList as $_info) {
                // 记录处理的最后一个id
                $lastId = $_info['id'];
                // 这个学生已经参加过活动
                if (isset($inActivityStudentId[$_info['id']])) {
                    continue;
                }
                $noJoinActivityStudent[] = $_info;
            }
            // 删除变量
            unset($studentList);
        }
        return $noJoinActivityStudent;
    }

    /**
     * 截图-活动列表
     * @param $params
     * @return array
     */
    public static function getSelectList($params)
    {
        list($page, $limit) = Util::formatPageCount($params);
        $limitOffset = [($page - 1) * $limit, $limit];
        list($list, $total) = WeekActivityModel::searchList($params, $limitOffset);
        if (empty($total)) {
            return [$list, $total];
        }
        foreach ($list as &$item) {
            $item['id'] = $item['activity_id'];
            $item = self::formatActivityTimeStatus($item);
        }
        return [$list, $total];
    }

    /**
     * 智能 - 检查活动id是不是新规则的活动id
     * @param $activityId
     * @return bool
     */
    public static function dssCheckActivityIsNew($activityId): bool
    {
        $oldRuleLastActivityId = RealDictConstants::get(RealDictConstants::REAL_ACTIVITY_CONFIG, 'old_rule_last_activity_id');
        if ($activityId <= $oldRuleLastActivityId) {
            return true;
        }
        return false;
    }

    /**
     * 智能 - 获取学生可参与的周周领奖活动列表
     * 排序规则： 按照活动优先级从小到大， 相同优先级的再按照活动ID从大到小排序
     * @param $studentInfo
     * @return array
     */
    public static function getDssStudentCanPartakeWeekActivityList($studentInfo): array
    {
        $time = time();
        $studentId = $studentInfo['student_id'] ?? 0;
        $studentUUID = $studentInfo['uuid'] ?? '';
        if (empty($studentUUID)) {
            $dssStudentInfo = DssStudentModel::getRecord(['id' => $studentId], ['uuid']);
            $studentUUID = $dssStudentInfo['uuid'] ?? '';
        }
        if (empty($studentId) || empty($studentUUID)) {
            return [];
        }
        $oldRuleLastActivityId = RealDictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'old_rule_last_activity_id');
        list($isNormalStudent, $studentIdAttribute) = UserService::checkDssStudentIdentityIsNormal($studentId);
        $activityList = [];
        if ($isNormalStudent) {
            /** 如果是付费有效用户，获取付费时间内可参与的活动列表 */
            // 获取所有当前时间启用中的活动信息列表
            $activityList = WeekActivityModel::getRecords([
                'start_time[<]' => $time,
                'end_time[>]' => $time,
                'enable_status' => OperationActivityModel::ENABLE_STATUS_ON,
                'activity_id[>]' => $oldRuleLastActivityId,
            ]);
            foreach ($activityList as $_activityKey => $_activityInfo) {
                // 过滤掉 目标用户类型是部分有效付费用户首次付费时间
                if ($_activityInfo['target_user_type'] == WeekActivityModel::TARGET_USER_PART) {
                    if ($studentIdAttribute['first_pay_time'] <= $_activityInfo['target_use_first_pay_time_start']) {
                        // 用户首次付费时间小于活动设定的首次付费起始时间，删除
                        unset($activityList[$_activityKey]);
                    }
                    if ($studentIdAttribute['first_pay_time'] > $_activityInfo['target_use_first_pay_time_end']) {
                        // 用户首次付费时间大于活动设定的首次付费截止时间， 删除
                        unset($activityList[$_activityKey]);
                    }
                }
            }
            unset($_activityKey, $_activityInfo);
        }
        // 获取所有添加了用户uuid的所有当期时间启用中的活动
        $designateActivityIdList = SharePosterDesignateUuidModel::getUUIDDesignateWeekActivityList($studentUUID, $time);
        $activityList = array_merge($activityList, $designateActivityIdList);
        // 没有查到任何活动，直接返回
        if (empty($activityList)) {
            return [];
        }
        $sortPriorityLevel = $sortActivityId = [];
        foreach ($activityList as $key => $item) {
            // 活动去重
            if (in_array($item['activity_id'], $sortActivityId)) {
                unset($activityList[$key]);
            }
            $sortPriorityLevel[] = $item['priority_level'];
            $sortActivityId[] = $item['activity_id'];
        }
        unset($key, $item);
        // 所有活动组合在一起，并且排序 - 排序规则，  优先级【数字越小代表优先级越高】 ---> 根据创建时间倒序【主键id倒序即可】
        array_multisort($sortPriorityLevel, SORT_ASC, $sortActivityId, SORT_DESC, $activityList);
        return $activityList;
    }

    /**
     * DSS - 获取周周领奖活动详情
     * @param $studentId
     * @param $activityId
     * @param $ext
     * @return array[]
     * @throws RunTimeException
     */
    public static function getWeekActivityData($studentId, $activityId = 0, $ext = [])
    {
        $type= 2;   // 2 代表的是周周领奖活动 - 兼容老逻辑时用的到
        $data = ['list' => [], 'activity' => []];
        $posterConfig = PosterService::getPosterConfig();
        $userDetail = StudentService::dssStudentStatusCheck($studentId, false, null);
        $userInfo = [
            'nickname' => $userDetail['student_info']['name'] ?? '',
            'headimgurl' => StudentService::getStudentThumb($userDetail['student_info']['thumb'])
        ];
        // 查询活动：
        $activityInfo = self::getDssStudentCanPartakeWeekActivityList(['student_id' => $studentId, 'uuid' => $userDetail['student_info']['uuid'] ?? ''])[0] ?? [];
        var_dump($activityInfo);exit;
        if (empty($activityInfo)) {
            return $data;
        }
        //练琴数据
        $practise = AprViewStudentModel::getStudentTotalSum($studentId);
        // 查询活动对应海报
        $posterList = PosterService::getActivityPosterList($activityInfo);
        if (empty($posterList)) {
            return $data;
        }
        $typeColumn             = array_column($posterList, 'type');
        $activityPosteridColumn = array_column($posterList, 'activity_poster_id');
        //周周领奖 海报排序处理
        if ($activityInfo['poster_order'] == TemplatePosterModel::POSTER_ORDER) {
            array_multisort($typeColumn, SORT_DESC, $activityPosteridColumn, SORT_ASC, $posterList);
        }
        $channel = PosterTemplateService::getChannel($type, $ext['from_type']);
        $extParams = [
            'user_current_status' => $userDetail['student_status'] ?? 0,
            'activity_id' => $activityInfo['activity_id'],
        ];

        // 组合生成海报数据
        $userQrParams = [];
        foreach ($posterList as &$item) {
            $_tmp = $extParams;
            $_tmp['poster_id'] = $item['poster_id'];
            $_tmp['user_id'] = $studentId;
            $_tmp['user_type'] = Constants::USER_TYPE_STUDENT;
            $_tmp['channel_id'] = $channel;
            $_tmp['landing_type'] = DssUserQrTicketModel::LANDING_TYPE_MINIAPP;
            $_tmp['date'] = date('Y-m-d',time());
            $userQrParams[] = $_tmp;
        }
        unset($item);
        // 获取小程序码
        $userQrArr = MiniAppQrService::batchCreateUserMiniAppQr(Constants::SMART_APP_ID, DssUserWeiXinModel::BUSI_TYPE_REFERRAL_MINAPP, $userQrParams);


        foreach ($posterList as $key => &$item) {
            $extParams['poster_id'] = $item['poster_id'];
            $item = PosterTemplateService::formatPosterInfo($item);
            if ($item['type'] == TemplatePosterModel::INDIVIDUALITY_POSTER) {
                $item['qr_code_url'] = AliOSS::replaceCdnDomainForDss($userQrArr[$key]['qr_path']);
                $word = [
                    'qr_id' => $userQrArr[$key]['qr_id'],
                    'date'  => date('m.d', time()),
                ];
                $item['poster_url'] = PosterService::addAliOssWordWaterMark($item['poster_path'], $word, $posterConfig);
                continue;
            }
            // 海报图：
            $poster = PosterService::generateQRPoster(
                $item['poster_path'],
                $posterConfig,
                $studentId,
                DssUserQrTicketModel::STUDENT_TYPE,
                $channel,
                $extParams,
                $userQrArr[$key] ?? []
            );
            $item['poster_url'] = $poster['poster_save_full_path'];
        }
        $activityInfo['ext'] = ActivityExtModel::getActivityExt($activityInfo['activity_id']);
        // 学生能否可上传


        $data['list'] = $posterList;
        $data['activity'] = $activityInfo;
        $data['student_info'] = $userInfo;
        $data['student_status'] = $userDetail['student_status'];
        $data['student_status_zh'] = DssStudentModel::STUDENT_IDENTITY_ZH_MAP[$userDetail['student_status']] ?? DssStudentModel::STATUS_REGISTER;
        $data['can_upload'] = SharePosterService::getStudentWeekActivityCanUpload($studentId, $activityId);           // 学生是否可上传
        $data['is_have_activity'] = !empty($activityInfo);    // 是否有周周领奖活动
        $data['practise'] = $practise;
        $data['uuid'] = $userDetail['student_info']['uuid'];
        return $data;
    }
}
