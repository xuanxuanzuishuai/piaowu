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
use App\Models\Dss\DssStudentModel;
use App\Models\MessagePushRulesModel;
use App\Models\MessageRecordModel;
use App\Models\OperationActivityModel;
use App\Models\RealSharePosterDesignateUuidModel;
use App\Models\RealSharePosterModel;
use App\Models\RealSharePosterPassAwardRuleModel;
use App\Models\RealSharePosterTaskListModel;
use App\Models\RealWeekActivityPosterAbModel;
use App\Models\TemplatePosterModel;
use App\Models\RealWeekActivityModel;
use App\Services\Queue\QueueService;
use App\Services\TraitService\TraitRealWeekActivityTestAbService;

class RealWeekActivityService
{
    use TraitRealWeekActivityTestAbService;

    /**
     * @param $data
     * @param $employeeId
     * @return array
     * @throws RunTimeException
     */
    public static function add($data, $employeeId): array
    {
        $returnData = [
            'activity_id' => 0
        ];
        $checkAllowAdd = self::checkAllowAdd($data, OperationActivityModel::TYPE_WEEK_ACTIVITY);
        if (!empty($checkAllowAdd)) {
            throw new RunTimeException([$checkAllowAdd]);
        }
        $time = time();
        $activityData = [
            'name' => $data['name'] ?? '',
            'app_id' => Constants::REAL_APP_ID,
            'create_time' => $time,
        ];
        $activityEndTime = Util::getDayLastSecondUnix($data['end_time']);
        $delaySendAwardTimeData = self::getActivityDelaySendAwardTime($activityEndTime, $data['award_prize_type'], $data['delay_day']);
        // 获取测试海报数据
        $weekActivityData = [
            'name' => $activityData['name'],
            'activity_id' => 0,
            'share_word' => !empty($data['share_word']) ? Util::textEncode($data['share_word']) : '',
            'start_time' => Util::getDayFirstSecondUnix($data['start_time']),
            'end_time' => $activityEndTime,
            'enable_status' => OperationActivityModel::ENABLE_STATUS_OFF,
            'banner' => $data['banner'] ?? '',
            'share_button_img' => $data['share_button_img'] ?? '',
            'award_detail_img' => $data['award_detail_img'] ?? '',
            'upload_button_img' => $data['upload_button_img'] ?? '',
            'strategy_img' => $data['strategy_img'] ?? '',
            'operator_id' => $employeeId,
            'create_time' => $time,
            'personality_poster_button_img' => $data['personality_poster_button_img'],
            'share_poster_prompt' => !empty($data['share_poster_prompt']) ? Util::textEncode($data['share_poster_prompt']) : '',
            'retention_copy' => !empty($data['retention_copy']) ? Util::textEncode($data['retention_copy']) : '',
            'poster_order' => $data['poster_order'],
            'target_user_type' => intval($data['target_user_type']),
            'target_use_first_pay_time_start' => !empty($data['target_use_first_pay_time_start']) ? strtotime($data['target_use_first_pay_time_start']) : 0,
            'target_use_first_pay_time_end' => !empty($data['target_use_first_pay_time_end']) ? strtotime($data['target_use_first_pay_time_end']) : 0,
            'delay_second' => $delaySendAwardTimeData['delay_second'],
            'send_award_time' => $delaySendAwardTimeData['send_award_time'],
            'award_prize_type' => $data['award_prize_type'],
            'clean_is_join' => $data['clean_is_join'],
            'activity_country_code' => trim($data['activity_country_code']) ?? 0,    // 0代表全部
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
            throw new RunTimeException(["add_week_operation_activity_fail"]);
        }
        // 保存实验组数据
        list($weekActivityData['has_ab_test'], $weekActivityData['allocation_mode']) = self::saveAllocationData($activityId, $data);
        // 保存周周领奖配置信息
        $weekActivityData['activity_id'] = $activityId;
        $weekActivityId = RealWeekActivityModel::insertRecord($weekActivityData);
        if (empty($weekActivityId)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add insert week_activity fail", ['data' => $weekActivityData]);
            throw new RunTimeException(["add_week_activity_fail"]);
        }
        // 保存周周领奖扩展信息
        $activityExtData['activity_id'] = $activityId;
        $activityExtId = ActivityExtModel::insertRecord($activityExtData);
        if (empty($activityExtId)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add insert activity_ext fail", ['data' => $activityExtData]);
            throw new RunTimeException(["add_week_activity_ext_fail"]);
        }
        // 保存海报关联关系
        $posterArray = array_merge($data['personality_poster'] ?? [], $data['poster'] ?? []);
        $activityPosterRes = ActivityPosterModel::batchInsertStudentActivityPoster($activityId, $posterArray);
        if (empty($activityPosterRes)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add batch insert activity_poster fail", ['data' => $data]);
            throw new RunTimeException(["add_week_activity_poster_fail"]);
        }
        // 保存分享任务
        $ruleRes = RealSharePosterTaskListModel::batchInsertActivityTask($activityId, $data['task_list'], $time);
        if (empty($ruleRes)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add batch insert real_share_poster_task_list fail", ['data' => $data]);
            throw new RunTimeException(["add_week_activity_task_fail"]);
        }
        // 保存分享任务奖励规则
        $ruleRes = RealSharePosterPassAwardRuleModel::batchInsertPassAwardRule($activityId, $data['task_list'], $time);
        if (empty($ruleRes)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add batch insert real_share_poster_pass_award fail", ['data' => $data]);
            throw new RunTimeException(["add_week_activity_pass_award_fail"]);
        }
        $db->commit();

        $returnData['activity_id'] = $activityId;
        return $returnData;
    }

    /**
     * 检查是否允许添加 - 检查添加必要的参数
     * @param $data
     * @param int $type
     * @return string
     */
    public static function checkAllowAdd($data, int $type = OperationActivityModel::TYPE_MONTH_ACTIVITY): string
    {
        // 海报不能为空
        if (empty($data['poster']) && $type == OperationActivityModel::TYPE_MONTH_ACTIVITY) {
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
        $targetUserType = !empty($data['target_user_type']) ? intval($data['target_user_type']) : 0;
        $startFirstPayTime = !empty($data['target_use_first_pay_time_start']) ?  strtotime($data['target_use_first_pay_time_start']) : 0;
        $endFirstPayTime = !empty($data['target_use_first_pay_time_end']) ?  strtotime($data['target_use_first_pay_time_end']) : 0;
        if ($targetUserType == RealWeekActivityModel::TARGET_USER_PART) {
            if ($startFirstPayTime <= 0 || $startFirstPayTime >= $endFirstPayTime) {
                return 'first_start_time_eq_end_time';
            }
        }
        // 分享任务不能为空
        if (empty($data['task_list'])) {
            return 'task_list_is_required';
        }
        if (count($data['task_list']) > 10) {
            return 'task_list_max_ten';
        }

        // 开启实验海报，则实验海报和标准海报都不能为空
        $checkAbData = self::checkAbPoster($data);
        if (!empty($checkAbData)) {
            return $checkAbData;
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
        list($list, $total) = RealWeekActivityModel::searchList($params, $limitOffset);

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
        $info['share_word'] = Util::textDecode($activityInfo['share_word']);
        $info['personality_poster_button_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['personality_poster_button_img']);
        $info['share_poster_prompt'] = Util::textDecode($activityInfo['share_poster_prompt']);
        $info['retention_copy'] = Util::textDecode($activityInfo['retention_copy']);
        $info['delay_day'] = $activityInfo['delay_second']/Util::TIMESTAMP_ONEDAY;
        $info['format_target_use_first_pay_time_start'] = !empty($activityInfo['target_use_first_pay_time_start']) ? date("Y-m-d H:i:s", $activityInfo['target_use_first_pay_time_start']) : '';
        $info['format_target_use_first_pay_time_end'] = !empty($activityInfo['target_use_first_pay_time_end']) ? date("Y-m-d H:i:s", $activityInfo['target_use_first_pay_time_end']) : '';
        $info['target_user_type'] = !empty($activityInfo['target_user_type']) ? $activityInfo['target_user_type'] : '';
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
     * 获取活动过程状态
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
     * 获取周周领奖详情
     * @param $activityId
     * @return mixed
     * @throws RunTimeException
     */
    public static function getDetailById($activityId)
    {
        $activityInfo = RealWeekActivityModel::getDetailByActivityId($activityId);
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
        $activityInfo['task_list'] = array_column(RealSharePosterPassAwardRuleModel::getRecords(['activity_id' => $activityId, 'ORDER' => ['success_pass_num' => 'ASC']]),'award_amount');

        //分享任务总数
        $activityInfo['task_num_count'] = count($activityInfo['task_list']);
        // 获取uuid
        $activityInfo['designate_uuid'] = RealSharePosterDesignateUuidModel::getUUIDByActivityId($activityId);
        // 获取测试海报数据
        // 获取测试海报数据
        $activityInfo['ab_test'] = [
            'has_ab_test' => $activityInfo['has_ab_test'],
            'allocation_mode' => $activityInfo['allocation_mode'],
            'distribution_type' => $activityInfo['allocation_mode'],
        ];
        list($activityInfo['ab_test']['control_group'], $activityInfo['ab_test']['ab_poster_list']) = self::getTestAbList($activityId);
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
        ];
        $checkAllowAdd = self::checkAllowAdd($data, OperationActivityModel::TYPE_WEEK_ACTIVITY);
        if (!empty($checkAllowAdd)) {
            throw new RunTimeException([$checkAllowAdd]);
        }
        $activityId = intval($data['activity_id']);
        $weekActivityInfo = RealWeekActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($weekActivityInfo)) {
            throw new RunTimeException(['record_not_found']);
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
        $activityEndTime = Util::getDayLastSecondUnix($data['end_time']);
        $delaySendAwardTimeData= self::getActivityDelaySendAwardTime($activityEndTime, $data['award_prize_type'], $data['delay_day']);
        $weekActivityData = [
            'activity_id' => $activityId,
            'name' => $activityData['name'],
            'share_word' => !empty($data['share_word']) ? Util::textEncode($data['share_word']) : '',
            'start_time' => Util::getDayFirstSecondUnix($data['start_time']),
            'end_time' => $activityEndTime,
            'banner' => $data['banner'] ?? '',
            'share_button_img' => $data['share_button_img'] ?? '',
            'award_detail_img' => $data['award_detail_img'] ?? '',
            'upload_button_img' => $data['upload_button_img'] ?? '',
            'strategy_img' => $data['strategy_img'] ?? '',
            'operator_id' => $employeeId,
            'update_time' => $time,
            'personality_poster_button_img' => $data['personality_poster_button_img'],
            'share_poster_prompt' => !empty($data['share_poster_prompt']) ? Util::textEncode($data['share_poster_prompt']) : '',
            'retention_copy' => !empty($data['retention_copy']) ? Util::textEncode($data['retention_copy']) : '',
            'poster_order' => $data['poster_order'],
        ];
        //区分不同启用状态可修改数据
        $discriminateStatusWeekActivityData = [
            'target_user_type' => !empty($data['target_user_type']) ? intval($data['target_user_type']) : 0,
            'target_use_first_pay_time_start' => !empty($data['target_use_first_pay_time_start']) ? strtotime($data['target_use_first_pay_time_start']) : 0,
            'target_use_first_pay_time_end' => !empty($data['target_use_first_pay_time_end']) ? strtotime($data['target_use_first_pay_time_end']) : 0,
            'delay_second' => $delaySendAwardTimeData['delay_second'],
            'send_award_time' => $delaySendAwardTimeData['send_award_time'],
            'award_prize_type' => $data['award_prize_type'],
            'clean_is_join' => $data['clean_is_join'],
        ];
        // 待启用才可以编辑的字段
        $weekActivityEnableStatudEditData = [
            'activity_country_code' => trim($data['activity_country_code']) ?? 0,    // 0代表全部
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
        // 更新周周领奖扩展信息
        $activityExtData['activity_id'] = $activityId;
        $res = ActivityExtModel::batchUpdateRecord($activityExtData, ['activity_id' => $activityId]);
        if (is_null($res)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add update activity_ext fail", ['data' => $weekActivityData, 'activity_id' => $activityId]);
            throw new RunTimeException(["update week activity fail"]);
        }
        // 保存实验组数据
        list($weekActivityData['has_ab_test'], $weekActivityData['allocation_mode']) = self::updateAllocationData($activityId, $data);
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
            $activityPosterRes = ActivityPosterModel::batchInsertStudentActivityPoster($activityId, $posterArray);
            if (empty($activityPosterRes)) {
                $db->rollBack();
                SimpleLogger::info("WeekActivityService:add batch insert activity_poster fail", ['data' => $weekActivityData, 'activity_id' => $activityId]);
                throw new RunTimeException(["add week activity fail"]);
            }
        }

        // 待启用的状态可以额外编辑 分享任务和uuid 等
        if ($weekActivityInfo['enable_status'] == OperationActivityModel::ENABLE_STATUS_OFF) {
            //更新分享任务
            $ruleRes = RealSharePosterTaskListModel::batchUpdateActivityTask($activityId, $data['task_list'], $time);
            if (empty($ruleRes)) {
                $db->rollBack();
                SimpleLogger::info("WeekActivityService:add batch insert real_share_poster_task_rule fail", ['data' => $data]);
                throw new RunTimeException(["add week activity fail"]);
            }
            // 更新分享通过奖励数据
            $ruleRes = RealSharePosterPassAwardRuleModel::batchUpdatePassAwardRule($activityId, $data['task_list'], $time);
            if (empty($ruleRes)) {
                $db->rollBack();
                SimpleLogger::info("WeekActivityService:add batch insert real_share_poster_task_rule fail", ['data' => $data]);
                throw new RunTimeException(["add week activity fail"]);
            }
            $weekActivityData = array_merge($weekActivityData, $weekActivityEnableStatudEditData);
        }
        if ($weekActivityInfo['enable_status'] != OperationActivityModel::ENABLE_STATUS_ON) {
            $weekActivityData = array_merge($weekActivityData, $discriminateStatusWeekActivityData);
        }
        // 更新周周领奖配置信息
        $res = RealWeekActivityModel::batchUpdateRecord($weekActivityData, ['activity_id' => $activityId]);
        if (is_null($res)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add update week_activity fail", ['data' => $weekActivityData, 'activity_id' => $activityId]);
            throw new RunTimeException(["update week activity fail"]);
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
    public static function editEnableStatus($activityId, $enableStatus, $employeeId): bool
    {
        if (!in_array($enableStatus, [OperationActivityModel::ENABLE_STATUS_ON, OperationActivityModel::ENABLE_STATUS_DISABLE])) {
            throw new RunTimeException(['enable_status_invalid']);
        }
        $activityInfo = RealWeekActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        if ($activityInfo['enable_status'] == $enableStatus) {
            return true;
        }
        if ($enableStatus == OperationActivityModel::ENABLE_STATUS_ON) {
            // 如果是启用活动 - 校验活动是否允许启动
            self::checkActivityIsAllowEnable($activityInfo);
        }
        // 修改启用状态
        $res = RealWeekActivityModel::updateRecord($activityInfo['id'], ['enable_status' => $enableStatus, 'operator_id' => $employeeId, 'update_time' => time()]);
        if (is_null($res)) {
            throw new RunTimeException(['update_failure']);
        }

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
     * 检查活动是否可以启用
     * @param $activityInfo
     * @return void
     * @throws RunTimeException
     */
    public static function checkActivityIsAllowEnable($activityInfo)
    {
        $conflictWhere = [
            'start_time[<=]' => $activityInfo['end_time'],
            'end_time[>=]'   => $activityInfo['start_time'],
            'enable_status'  => OperationActivityModel::ENABLE_STATUS_ON,
        ];
        // 如果活动指定了投放地区，搜索时需要区分投放地区
        $activityInfo['activity_country_code'] && $conflictWhere['activity_country_code'] = [OperationActivityModel::ACTIVITY_COUNTRY_ALL, $activityInfo['activity_country_code']];
        // 清退用户同一时刻同一区域只能启用一个
        if ($activityInfo['clean_is_join'] == RealWeekActivityModel::CLEAN_IS_JOIN_YES) {
            $cleanConflictWhere = $conflictWhere;
            $cleanConflictWhere['clean_is_join'] = RealWeekActivityModel::CLEAN_IS_JOIN_YES;
            $conflictData = RealWeekActivityModel::getCount($cleanConflictWhere);
            if ($conflictData > 0) {
                throw new RunTimeException(['activity_conflict']);
            }
        }
        if ($activityInfo['target_user_type'] == RealWeekActivityModel::TARGET_USER_PART) {
            //启用的活动目标用户是部分
            $conflictWhere['OR'] =  [
                    'AND #one' => [
                        'target_user_type' => RealWeekActivityModel::TARGET_USER_ALL,
                    ],
                    'AND #two' => [
                        'target_use_first_pay_time_start[<=]' => $activityInfo['target_use_first_pay_time_end'],
                        'target_use_first_pay_time_end[>=]'   => $activityInfo['target_use_first_pay_time_start'],
                        'target_user_type'                    => RealWeekActivityModel::TARGET_USER_PART,
                    ],
            ];
        }
        $conflictData = RealWeekActivityModel::getCount($conflictWhere);
        if ($conflictData > 0) {
            throw new RunTimeException(['activity_conflict']);
        }
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
        $activityInfo = RealWeekActivityModel::getRecord(['activity_id' => $activityId]);
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
            $i++;
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
        $activityInfo = RealWeekActivityModel::getRecord(['activity_id' => $activityId]);
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
        $inActivityStudent = RealSharePosterModel::getRecords(['activity_id' => $activityId], ['student_id']);
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
        list($list, $total) = RealWeekActivityModel::searchList($params, $limitOffset);
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
     * 获取学生可参与的周周领奖活动列表
     * @param $studentInfo
     * @param int $operationType    操作类型:1获取当前生效的活动 2获取补卡活动
     * @return array
     */
    public static function getStudentCanPartakeWeekActivityList($studentInfo, $operationType = 1): array
    {
        $time = time();
        $studentId = $studentInfo['student_id'] ?? ($studentInfo['id'] ?? 0);
        $studentUUID = $studentInfo['uuid'] ?? '';
        if (empty($studentId) || empty($studentUUID)) {
            return [];
        }
        // 获取用户身份属性
        $studentIdAttribute = UserService::getStudentIdentityAttributeById(Constants::REAL_APP_ID, $studentId, $studentUUID);
        // 获取活动列表
        $baseWhere = [
            "start_time[<]" => $time,
            'enable_status' => OperationActivityModel::ENABLE_STATUS_ON,
            'target_user_type[!]' => 0,
            "ORDER" => ['end_time' => 'DESC']
        ];
        if ($operationType == 1) {
            //当前生效的活动
            $baseWhere['end_time[>=]'] = $time;
        } elseif ($operationType == 2) {
            //已结束并启用的活动：结束时间距离当前时间五天内
            $activityOverAllowUploadSecond = RealDictConstants::get(RealDictConstants::REAL_ACTIVITY_CONFIG, 'activity_over_allow_upload_second');
            $baseWhere['end_time[>=]'] = $time - $activityOverAllowUploadSecond;
            $baseWhere['end_time[<]'] = $time;
        } else {
            return [];
        }
        $baseWhere['activity_country_code'] = OperationActivityModel::getStudentWeekActivityCountryCode($studentInfo, Constants::REAL_APP_ID);
        //获取活动
        $activityList = RealWeekActivityModel::getRecords($baseWhere);
        foreach ($activityList as $_activityKey => $_activityInfo) {
            // 检查一下用户是否是有效用户，不是有效用户不可能有可参与的活动
            if (!UserService::checkRealStudentIdentityIsNormal($studentId, $studentIdAttribute)) {
                unset($activityList[$_activityKey]);
                continue;
            }
            //检测用户首次付费时间与活动结束时间大小关系
            if ($studentIdAttribute['first_pay_time'] > $_activityInfo['end_time']) {
                unset($activityList[$_activityKey]);
                continue;
            }
            /**
             * 清退再续费用户定义：清退用户&首次清退后再续费&当前付费有效
             * 优先级：清退再续费用户 》活动对象：
             * 1。活动此选项选择"是"：可以参与
             * 2。活动此选项选择"否"：不可参与
             */
            if (($studentIdAttribute['is_cleaned'] == Constants::STATUS_TRUE) && ($studentIdAttribute['buy_after_clean'] == Constants::STATUS_TRUE)) {
                if ($_activityInfo['clean_is_join'] == RealWeekActivityModel::CLEAN_IS_JOIN_YES) {
                    continue;
                } else {
                    unset($activityList[$_activityKey]);
                    continue;
                }
            } elseif ($_activityInfo['target_user_type'] == RealWeekActivityModel::TARGET_USER_PART) {
                // 过滤掉 目标用户类型是部分有效付费用户首次付费时间
                if ($studentIdAttribute['first_pay_time'] <= $_activityInfo['target_use_first_pay_time_start']) {
                    // 用户首次付费时间小于活动设定的首次付费起始时间，删除
                    unset($activityList[$_activityKey]);
                    continue;
                }
                if ($studentIdAttribute['first_pay_time'] > $_activityInfo['target_use_first_pay_time_end']) {
                    // 用户首次付费时间大于活动设定的首次付费截止时间， 删除
                    unset($activityList[$_activityKey]);
                    continue;
                }
            }
        }
        // 没有查到任何活动，直接返回
        if (empty($activityList)) {
            return [];
        }
        return array_values($activityList);
    }


    /**
     * 计算活动发奖时间
     * @param $activityEndTime
     * @param $awardPrizeType
     * @param int $delayDay
     * @return array
     */
    public static function getActivityDelaySendAwardTime($activityEndTime, $awardPrizeType, $delayDay = 0)
    {
        /**
         * 计算发奖时间
         * 发放奖励时间公式：   M(发放奖励时间) = 活动结束时间(天) + 5天 + N天
         * example: 活动结束时间是1号23:59:59， 发放奖励时间是 5+1天 ， 则  M= 1+5+1 = 7, 得出是在7号12点发放奖励
         */
        $data = [
            'delay_second' => 0,
            'send_award_time' => 0,
        ];
        if ($awardPrizeType == OperationActivityModel::AWARD_PRIZE_TYPE_DELAY) {
            $data['delay_second'] = !empty($delayDay) ? $delayDay * Util::TIMESTAMP_ONEDAY : 0;
            $sendAwardBaseDelaySecond = RealDictConstants::get(RealDictConstants::REAL_ACTIVITY_CONFIG, 'send_award_base_delay_second');
            $data['send_award_time'] = Util::getStartEndTimestamp($activityEndTime)[0] + $sendAwardBaseDelaySecond + $data['delay_second'];
        }
        return $data;
    }

    /**
     * 格式化处理活动名称
     * @param $activityData
     * @return string
     */
    public static function formatWeekActivityName($activityData)
    {
        $taskNumCount = empty($activityData['task_num_count']) ? '1' : $activityData['task_num_count'];
        $timeFormat = '(' . date("m.d", $activityData['start_time']) . '-' . date("m.d", $activityData['end_time']) . ')';
        return $taskNumCount . '次分享截图活动' . $timeFormat;
    }

    /**
     * 格式化处理活动分享任务名称
     * @param $activityTaskData
     * @return string
     */
    public static function formatWeekActivityTaskName($activityTaskData)
    {
        $taskNumCount = empty($activityTaskData['task_num_count']) ? '1' : $activityTaskData['task_num_count'];
        $timeFormat = '(' . date("m.d", $activityTaskData['start_time']) . '-' . date("m.d", $activityTaskData['end_time']) . ')';
        $taskNum = empty($activityTaskData['task_num']) ? 1 : $activityTaskData['task_num'];
        return $taskNumCount . '次分享截图活动-'.$taskNum .$timeFormat;
    }
}
