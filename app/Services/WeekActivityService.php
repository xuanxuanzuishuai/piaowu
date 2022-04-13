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
use App\Services\TraitService\TraitWeekActivityTestAbService;
use I18N\Lang;
use Medoo\Medoo;

class WeekActivityService
{
    use TraitWeekActivityTestAbService;
    const WEEK_ACTIVITY_TYPE = 1; //周周活动类型
    const MONTH_ACTIVITY_TYPE = 2; //月月活动类型

    const ACTIVITY_RETRY_UPLOAD_NO = 1; //1当前没有具备补卡资格的活动
    const ACTIVITY_RETRY_UPLOAD_ALL_PASS = 2;   // 2具备补卡资格的活动分享任务都已通过
    const ACTIVITY_RETRY_YES = 3;   // 3存在有效的补卡活动

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
        $activityEndTime = Util::getDayLastSecondUnix($data['end_time']);
        $delaySendAwardTimeData = self::getActivityDelaySendAwardTime($activityEndTime, $data['award_prize_type'], $data['delay_day']);
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
            'delay_second' => $delaySendAwardTimeData['delay_second'],
            'send_award_time' => $delaySendAwardTimeData['send_award_time'],
            'priority_level' => $data['priority_level'] ?? 0,
            'activity_country_code' => intval($data['activity_country_code']),    // 0代表全部
            'award_prize_type' =>$data['award_prize_type'],
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
        // 保存实验组数据
        list($weekActivityData['has_ab_test'], $weekActivityData['allocation_mode']) = self::saveAllocationData($activityId, $data, $employeeId);
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
        $activityPosterRes = ActivityPosterModel::batchInsertStudentActivityPoster($activityId, $posterArray);
        if (empty($activityPosterRes)) {
            $db->rollBack();
            SimpleLogger::info("WeekActivityService:add batch insert activity_poster fail", ['data' => $data]);
            throw new RunTimeException(["add week activity fail"]);
        }
        // 保存分享任务
        $ruleRes = SharePosterTaskListModel::batchInsertActivityTask($activityId, $data['task_list'], $time, $activityData['name']);
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

        // 周周领奖 - 分享任务不能为空
        if ($type == self::WEEK_ACTIVITY_TYPE) {
            if (empty($data['task_list'])) {
                return 'task_list_is_required';
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
            if (count($data['task_list']) > WeekActivityModel::MAX_TASK_NUM) {
                return 'task_list_max_ten';
            }

            // 开启实验海报，则实验海报和标准海报都不能为空
            $checkAbData = self::checkAbPoster($data);
            if (!empty($checkAbData)) {
                return $checkAbData;
            }
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
        // 获取奖励
        $activityInfo['pass_award_rule_list'] = SharePosterPassAwardRuleModel::getRecords(['activity_id' => $activityId, 'ORDER' => ['success_pass_num' => 'ASC']]);
        // 获取活动奖励数组
        $activityInfo['task_list'] = array_column($activityInfo['pass_award_rule_list'] ,'award_amount');
        // 获取uuid
        $activityInfo['designate_uuid'] = SharePosterDesignateUuidModel::getUUIDByActivityId($activityId);
        // 获取测试海报数据
        $ab_test = [
            'has_ab_test' => (int)$activityInfo['has_ab_test'],
            'allocation_mode' => (int)$activityInfo['allocation_mode'],
            'distribution_type' => (int)$activityInfo['allocation_mode'],
        ];
        list($ab_test['control_group'], $ab_test['ab_poster_list']) = self::getTestAbList($activityId);
        // 有实验组数据则返回实验组海报列表
        if ($ab_test['ab_poster_list']) {
            $activityInfo['ab_test'] = $ab_test;

        }
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
        $delaySendAwardTimeData = self::getActivityDelaySendAwardTime($activityEndTime, $data['award_prize_type'], $data['delay_day']);
        $weekActivityData = [
            'activity_id' => $activityId,
            'name' => $activityData['name'],
            'guide_word' => !empty($data['guide_word']) ? Util::textEncode($data['guide_word']) : '',
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
            'poster_prompt' => !empty($data['poster_prompt']) ? Util::textEncode($data['poster_prompt']) : '',
            'poster_make_button_img' => $data['poster_make_button_img'],
            'share_poster_prompt' => !empty($data['share_poster_prompt']) ? Util::textEncode($data['share_poster_prompt']) : '',
            'retention_copy' => !empty($data['retention_copy']) ? Util::textEncode($data['retention_copy']) : '',
            'poster_order' => $data['poster_order'],
            'target_user_type' => intval($data['target_user_type']),
            'target_use_first_pay_time_start' => !empty($data['target_use_first_pay_time_start']) ? strtotime($data['target_use_first_pay_time_start']) : 0,
            'target_use_first_pay_time_end' => !empty($data['target_use_first_pay_time_end']) ? strtotime($data['target_use_first_pay_time_end']) : 0,
            'delay_second' => $delaySendAwardTimeData['delay_second'],
            'send_award_time' => $delaySendAwardTimeData['send_award_time'],
            'priority_level' => $data['priority_level'] ?? 0,
        ];
        // 待启用才可以编辑的字段
        $weekActivityEnableStatusEditData = [
            'activity_country_code' => !empty($data['activity_country_code']) ? intval($data['activity_country_code']) : 0,    // 0代表全部
            'award_prize_type' =>!empty($data['award_prize_type']) ? intval($data['award_prize_type']) : 0,
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
        // 更新实验组数据
        list($weekActivityData['has_ab_test'], $weekActivityData['allocation_mode']) = self::updateAllocationData($activityId, $data, $employeeId);
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
            $ruleRes = SharePosterTaskListModel::batchUpdateActivityTask($activityId, $data['task_list'], $time, $activityData['name']);
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
            // 只有待启用允许更新的字段加入到需要更新的数据中去
            $weekActivityData = array_merge($weekActivityData, $weekActivityEnableStatusEditData);
        }
        // 更新周周领奖配置信息
        $res = WeekActivityModel::batchUpdateRecord($weekActivityData, ['activity_id' => $activityId]);
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
    public static function editEnableStatus($activityId, $enableStatus, $employeeId)
    {
        if (!in_array($enableStatus, [OperationActivityModel::ENABLE_STATUS_ON, OperationActivityModel::ENABLE_STATUS_DISABLE])) {
            throw new RunTimeException(['enable_status_invalid']);
        }
        $activityInfo = WeekActivityModel::getRecord(['activity_id' => $activityId]);
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
        $res = WeekActivityModel::updateRecord($activityInfo['id'], ['enable_status' => $enableStatus, 'operator_id' => $employeeId, 'update_time' => time()]);
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
     * @param $studentId
     * @param string $studentUUID
     * @param null $activityCountryCode null:代表获取所有， 如果传入空字符串会获取学生id对应的country_code, 不为空直接使用
     * @return array
     */
    public static function getDssStudentCanPartakeWeekActivityList($studentId, string $studentUUID = '', $activityCountryCode = null): array
    {
        $time = time();
        // 如果activity_country_code不是null并且是空则用学生信息的country_code;
        // 如果uuid没有传入，需要补充uuid
        if ((!is_null($activityCountryCode) && empty($activityCountryCode)) || empty($studentUUID)) {
            $studentInfo = DssStudentModel::getRecord(['id' => $studentId], ['uuid', 'country_code']);
            $studentUUID = $studentInfo['uuid'] ?? '';
            !is_null($activityCountryCode) && empty($activityCountryCode) && $activityCountryCode = $studentInfo['country_code'];
        }
        if (empty($studentId) || empty($studentUUID)) {
            return [];
        }
        $oldRuleLastActivityId = DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'old_rule_last_activity_id');
        list($isNormalStudent, $studentIdAttribute) = UserService::checkDssStudentIdentityIsNormal($studentId);
        if ($isNormalStudent) {
            /** 如果是付费有效用户，获取付费时间内可参与的活动列表 */
            // 获取所有当前时间启用中的活动信息列表
            $where = [
                'start_time[<]' => $time,
                'end_time[>]' => $time,
                'enable_status' => OperationActivityModel::ENABLE_STATUS_ON,
                'activity_id[>]' => $oldRuleLastActivityId,
                'target_user_type[!]' => 0,
            ];
            !empty($activityCountryCode) && $where['activity_country_code'] = OperationActivityModel::getStudentWeekActivityCountryCode(['country_code' => $activityCountryCode]);
            $activityList = WeekActivityModel::getRecords($where);
            foreach ($activityList as $_activityKey => $_activityInfo) {
                // 过滤掉 目标用户类型是部分有效付费用户首次付费时间
                if ($_activityInfo['target_user_type'] == WeekActivityModel::TARGET_USER_PART) {
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
            unset($_activityKey, $_activityInfo);
        }
        // 获取所有添加了用户uuid的所有当期时间启用中的活动
        $designateActivityIdList = SharePosterDesignateUuidModel::getUUIDDesignateWeekActivityList($studentUUID, $time);
        $activityList = !empty($activityList) ? array_merge($activityList, $designateActivityIdList) : $designateActivityIdList;
        // 没有查到任何活动，直接返回
        if (empty($activityList)) {
            return [];
        }
        $sortPriorityLevel = $sortActivityId = [];
        foreach ($activityList as $key => $item) {
            // 活动去重
            if (in_array($item['activity_id'], $sortActivityId)) {
                unset($activityList[$key]);
                continue;
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
    public static function getWeekActivityData($studentId, $ext = [])
    {
        $data = [
            'list' => [],   // 海报列表
            'activity' => [],   // 活动详情
            'student_info' => [],   // 学生详情
            "is_have_activity" => false,   // 是否有可参与的活动
            "no_re_activity_reason" => 1,   // 是否有补卡资格， 1没有
        ];
        $posterConfig = PosterService::getPosterConfig();
        $userDetail = StudentService::dssStudentStatusCheck($studentId, false, null);
        if (empty($userDetail['student_info'])) {
            // 如果学生不存在，直接返回空
            return $data;
        }
        $userInfo = [
            'nickname' => $userDetail['student_info']['name'] ?? '',
            'headimgurl' => StudentService::getStudentThumb($userDetail['student_info']['thumb'])
        ];

        // 检查是否有补卡活动
        $retryUploadActivityList = self::checkStudentRetryUpload(['student_id' => $studentId]);
        $data['no_re_activity_reason'] = $retryUploadActivityList['no_re_activity_reason'];

        // 查询活动：
        $uuid = $userDetail['student_info']['uuid'] ?? '';
        $activityCountryCode = $userDetail['student_info']['country_code'] ?? '';
        $activityInfo = self::getDssStudentCanPartakeWeekActivityList($studentId, $uuid, $activityCountryCode)[0] ?? [];
        if (empty($activityInfo)) {
            return $data;
        }
        // 格式化活动信息
        $activityInfo = ActivityService::formatData($activityInfo);
        //练琴数据
        $practise = AprViewStudentModel::getStudentTotalSum($studentId);
        // 查询活动对应海报
        $posterList = PosterService::getActivityPosterList($activityInfo);
        if (empty($posterList)) {
            return $data;
        }
        $typeColumn = array_column($posterList, 'type');
        $activityPosterIdColumn = array_column($posterList, 'activity_poster_id');
        //周周领奖 海报排序处理
        if ($activityInfo['poster_order'] == TemplatePosterModel::POSTER_ORDER) {
            array_multisort($typeColumn, SORT_DESC, $activityPosterIdColumn, SORT_ASC, $posterList);
        }
        $channel = PosterTemplateService::getChannel(OperationActivityModel::TYPE_WEEK_ACTIVITY, $ext['from_type']);
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
            $_tmp['date'] = date('Y-m-d', time());
            $userQrParams[] = $_tmp;
        }
        unset($item);
        // 获取小程序码
        $userQrArr = MiniAppQrService::batchCreateUserMiniAppQr(Constants::SMART_APP_ID, DssUserWeiXinModel::BUSI_TYPE_REFERRAL_MINAPP, $userQrParams);

        // 获取AB测海报，和对照组海报id
        $abTestPosterInfo = WeekActivityService::getStudentTestAbPoster($studentId, $activityInfo['activity_id'], [
            'channel_id'      => $channel,
            'user_type'       => DssUserQrTicketModel::STUDENT_TYPE,
            'landing_type'    => DssUserQrTicketModel::LANDING_TYPE_MINIAPP,
            'user_status'     => $userDetail['student_status'],
            'is_create_qr_id' => true,
        ]);
        foreach ($posterList as $key => &$item) {
            $extParams['poster_id'] = $item['poster_id'];
            $item = PosterTemplateService::formatPosterInfo($item);
            if ($item['type'] == TemplatePosterModel::INDIVIDUALITY_POSTER) {
                $item['qr_code_url'] = AliOSS::replaceCdnDomainForDss($userQrArr[$key]['qr_path']);
                $word = [
                    'qr_id' => $userQrArr[$key]['qr_id'],
                    'date' => date('m.d', time()),
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
        list($isCanUpload) = WeekActivityService::getStudentWeekActivityCanUpload($studentId, $activityInfo['activity_id']);

        $data['list'] = $posterList;
        $data['activity'] = $activityInfo;
        $data['student_info'] = $userInfo;
        $data['student_status'] = $userDetail['student_status'];
        $data['student_status_zh'] = DssStudentModel::STUDENT_IDENTITY_ZH_MAP[$userDetail['student_status']] ?? DssStudentModel::STATUS_REGISTER;
        $data['can_upload'] = $isCanUpload;           // 学生是否可上传
        $data['is_have_activity'] = !empty($activityInfo);    // 是否有周周领奖活动
        $data['practise'] = $practise;
        $data['uuid'] = $userDetail['student_info']['uuid'];
        return $data;
    }

    /**
     * 获取用户身份命中周周领奖活动
     * @param $studentData
     * @return array
     */
    public static function getCanPartakeWeekActivity($studentData): array
    {
        // 获取用户信息
        $studentInfo = DssStudentModel::getRecord(['id' => $studentData['id']]);
        if (empty($studentInfo)) {
            return [];
        }
        // 获取用户可参与的活动
        $activityInfo = WeekActivityService::getDssStudentCanPartakeWeekActivityList($studentInfo['id'], $studentInfo['uuid'], $studentInfo['country_code'])[0] ?? 0;
        // 获取补卡的活动列表
        $retryUploadActivityList = self::getRetryUploadActivityList($studentInfo['id'], $studentInfo['uuid'], $studentInfo['country_code']);
        // 排序 - 当前活动未超过24小时  当期-往期(活动开始时间倒序)，  当期活动超过24小时 往期(活动开始时间倒序)-当期
        if (!empty($retryUploadActivityList) && !empty($activityInfo)) {
            if (time() - $activityInfo['start_time'] > Util::TIMESTAMP_ONEDAY) {
                $newActivityList = array_merge([$activityInfo], $retryUploadActivityList);
            } else {
                $newActivityList = array_merge($retryUploadActivityList, [$activityInfo]);
            }
        } elseif (!empty($retryUploadActivityList) && empty($activityInfo)) {
            // 只有补卡，没有命中
            $newActivityList = $retryUploadActivityList;
        } else {
            // 只有命中活动，没有补卡
            $newActivityList = [$activityInfo];
        }
        $result = [];
        if (!empty($newActivityList)) {
            // 获取活动奖励信息
            $passAwardList = SharePosterPassAwardRuleModel::getPassAwardRuleList(array_column($newActivityList, 'activity_id'));
            $passAwardList = array_column($passAwardList, null, "activity_id");
            foreach($newActivityList as $item) {
                list($studentIsNormal, $activityTaskList, $diffActivityTaskNum) = self::getStudentWeekActivityCanUpload($studentInfo['id'], $item['activity_id']);
                if (!$studentIsNormal) {
                    continue;
                }
                // 拼接可参与活动的列表
                foreach ($activityTaskList as $_taskInfo) {
                    $tmpInfo = $passAwardList[$_taskInfo['activity_id']];
                    $result[] = [
                        'activity_id' => $_taskInfo['activity_id'],
                        'task_num' => $_taskInfo['task_num'],
                        'name' => SharePosterService::formatWeekActivityTaskName([
                            'task_num_count' => $tmpInfo['success_pass_num'],
                            'task_num' => $_taskInfo['task_num'],
                            'start_time' => $tmpInfo['start_time'],
                            'end_time' => $tmpInfo['end_time'],
                        ]),
                    ];
                }
                unset($_taskInfo);
            }
            unset($item);
        }
        return $result ?? [];
    }

    /**
     * 检查学生能否上传： 如果已经全部参与也属于不能上传
     * @param $studentId
     * @param $activityId
     * @return array
     */
    public static function getStudentWeekActivityCanUpload($studentId, $activityId): array
    {
        // 获取活动任务列表
        $activityTaskList = SharePosterTaskListModel::getRecords(['activity_id' => $activityId, 'ORDER' => ['task_num' => 'ASC']]);
        if (empty($activityTaskList)) {
            return [false, [], []];
        }
        // 查看学生可参与的活动中已经审核通过的分享任务
        $haveQualifiedActivityIds = SharePosterModel::getRecords([
            'student_id' => $studentId,
            'activity_id' => $activityId,
            'verify_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED,
        ], 'task_num');
        // 查看学生相对可参与活动的状态 - 计算差集
        $diffActivityTaskNum = array_diff(array_column($activityTaskList, 'task_num'), $haveQualifiedActivityIds);
        if (empty($diffActivityTaskNum)) {
            return [false, $activityTaskList, $diffActivityTaskNum];
        }
        // 返回未参与的活动任务
        foreach ($activityTaskList as $key => $item) {
            if (!in_array($item['task_num'], $diffActivityTaskNum)) {
                unset($activityTaskList[$key]);
            }
        }
        unset($key, $item);
        return [true, $activityTaskList, $diffActivityTaskNum];
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
     * 检查周周领奖活动是否可以启用
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
        $activityInfo['activity_country_code'] && $conflictWhere['activity_country_code'] = OperationActivityModel::getWeekActivityCountryCode($activityInfo['activity_country_code']);
        if ($activityInfo['target_user_type'] == WeekActivityModel::TARGET_USER_PART) {
            //启用的活动目标用户是部分
            $conflictWhere['OR'] =  [
                'AND #one' => [
                    'target_user_type' => WeekActivityModel::TARGET_USER_ALL,
                ],
                'AND #two' => [
                    'target_use_first_pay_time_start[<=]' => $activityInfo['target_use_first_pay_time_end'],
                    'target_use_first_pay_time_end[>=]'   => $activityInfo['target_use_first_pay_time_start'],
                    'target_user_type'                    => WeekActivityModel::TARGET_USER_PART,
                ],
            ];
        }
        $conflictData = WeekActivityModel::getCount($conflictWhere);
        if ($conflictData > 0) {
            SimpleLogger::info("checkActivityIsAllowEnable_has_conflict", [$conflictWhere, $conflictData]);
            throw new RunTimeException(['activity_conflict']);
        }
    }

    /**
     * 检查学生是否有可以重新上传截图的活动:以结束并结束时间距离现在5天内
     * @param $studentInfo
     * @return array 1当前没有具备补卡资格的活动 2具备补卡资格的活动分享任务都已通过 3存在有效的补卡活动
     */
    public static function checkStudentRetryUpload($studentInfo)
    {
        $reCardActivity = [
            'no_re_activity_reason' => self::ACTIVITY_RETRY_UPLOAD_NO,
            'list' => [],
        ];
        $studentId = $studentInfo['student_id'] ?? ($studentInfo['id'] ?? 0);
        // 获取补卡列表
        $activityList = self::getRetryUploadActivityList($studentId, "", "");
        if (empty($activityList)) {
            return $reCardActivity;
        }
        $activityIds = array_column($activityList, 'activity_id');
        // 获取活动任务列表
        $activityTaskList = SharePosterPassAwardRuleModel::getActivityTaskList($activityIds);
        if (empty($activityTaskList)) {
            return $reCardActivity;
        }
        $activityTaskList = array_column($activityTaskList, null, 'activity_task');
        // 查看学生可参与的活动中已经审核通过的分享任务
        $haveQualifiedActivityIds = SharePosterModel::getRecords([
            'student_id' => $studentInfo['student_id'],
            'activity_id' => $activityIds,
            'verify_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED,
            'task_num' => array_unique(array_column($activityTaskList, 'success_pass_num')),
        ], ["activity_task" => Medoo::raw('concat_ws(:separator,activity_id,task_num)', [":separator" => '-'])]);
        // 差集
        $diffActivityTaskNum = array_diff(array_column($activityTaskList, 'activity_task'), array_column($haveQualifiedActivityIds, 'activity_task'));
        if (empty($diffActivityTaskNum)) {
            $reCardActivity['no_re_activity_reason'] = self::ACTIVITY_RETRY_UPLOAD_ALL_PASS;
        } else {
            // 交集
            $canPartakeActivity = array_intersect_key($activityTaskList, array_flip($diffActivityTaskNum));
            array_multisort(array_column($canPartakeActivity, 'start_time'), SORT_DESC, array_column($canPartakeActivity, 'success_pass_num'), SORT_ASC, $canPartakeActivity);
            $reCardActivity['no_re_activity_reason'] = self::ACTIVITY_RETRY_YES;
            $reCardActivity['list'] = $canPartakeActivity;
        }

        return $reCardActivity;
    }

    /**
     * 获取学生是否可以补卡，以及可以补卡的活动列表
     * @param $studentId
     * @param string $studentUUID
     * @param null $activityCountryCode null:代表获取所有， 如果传入空字符串会获取学生id对应的country_code, 不为空直接使用
     * @return array
     */
    public static function getRetryUploadActivityList($studentId, string $studentUUID = '', $activityCountryCode = null): array
    {
        $time = time();
        // 如果activity_country_code不是null并且是空则用学生信息的country_code;
        // 如果uuid没有传入，需要补充uuid
        if ((!is_null($activityCountryCode) && empty($activityCountryCode)) || empty($studentUUID)) {
            $studentInfo = DssStudentModel::getRecord(['id' => $studentId], ['uuid', 'country_code']);
            $studentUUID = $studentInfo['uuid'] ?? '';
            !is_null($activityCountryCode) && empty($activityCountryCode) && $activityCountryCode = $studentInfo['country_code'];
        }
        if (empty($studentId) || empty($studentUUID)) {
            return [];
        }
        $oldRuleLastActivityId = DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'old_rule_last_activity_id');
        list($isNormalStudent, $studentIdAttribute) = UserService::checkDssStudentIdentityIsNormal($studentId);
        if ($isNormalStudent) {
            /** 如果是付费有效用户， 获取5天内结束的活动列表，允许补卡*/
            $allowRetryUploadTime = DictConstants::get(DictConstants::DSS_WEEK_ACTIVITY_CONFIG, 'activity_over_allow_upload_second');
            // 因为是补卡所有用户首次付费时间之前活动都不能参与 结束时间必须在用户首次付费时间之后
            $timeOutTime = $studentIdAttribute['first_pay_time'] > ($time - $allowRetryUploadTime) ? $studentIdAttribute['first_pay_time'] : ($time - $allowRetryUploadTime);
            $where = [
                'end_time[>]' => $timeOutTime,   // 没有超过5天
                'end_time[<]' => $time,     // 已经结束
                'enable_status' => OperationActivityModel::ENABLE_STATUS_ON,
                'activity_id[>]' => $oldRuleLastActivityId,
                'target_user_type[!]' => 0,
                "ORDER" => ['end_time' => 'DESC']
            ];
            !empty($activityCountryCode) && $where['activity_country_code'] = OperationActivityModel::getStudentWeekActivityCountryCode(['country_code' => $activityCountryCode]);
            $activityList = WeekActivityModel::getRecords($where);
            foreach ($activityList as $_activityKey => $_activityInfo) {
                // 过滤掉 目标用户类型是部分有效付费用户首次付费时间
                if ($_activityInfo['target_user_type'] == WeekActivityModel::TARGET_USER_PART) {
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
            unset($_activityKey, $_activityInfo);
        }
        // 获取所有添加了用户uuid的所有当期时间启用中的活动
        $designateActivityIdList = SharePosterDesignateUuidModel::getUUIDDesignateWeekActivityList($studentUUID, $time);
        $activityList = !empty($activityList) ? array_merge($activityList, $designateActivityIdList) : $designateActivityIdList;
        // 没有查到任何活动，直接返回
        if (empty($activityList)) {
            return [];
        }
        $sortPriorityLevel = $sortActivityId = [];
        foreach ($activityList as $key => $item) {
            // 活动去重
            if (in_array($item['activity_id'], $sortActivityId)) {
                unset($activityList[$key]);
                continue;
            }
            $sortPriorityLevel[] = $item['priority_level'];
            $sortActivityId[] = $item['activity_id'];
        }
        unset($key, $item);
        // 所有活动组合在一起，并且排序 - 排序规则，  优先级【数字越小代表优先级越高】 ---> 根据创建时间倒序【主键id倒序即可】
        array_multisort($sortPriorityLevel, SORT_ASC, $sortActivityId, SORT_DESC, $activityList);
        return $activityList;
    }
}
