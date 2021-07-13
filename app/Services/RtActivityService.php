<?php
/**
 * rt优惠券活动业务逻辑处理
 */

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\ActivityExtModel;
use App\Models\ActivityPosterModel;
use App\Models\OperationActivityModel;
use App\Models\RtActivityModel;
use App\Models\RtActivityRuleModel;

class RtActivityService
{
    /**
     * 检查是否允许添加 - 检查添加必要的参数
     * @param $data
     * @return string
     */
    public static function checkAllowAdd($data)
    {
        // 海报不能为空
        if (empty($data['poster'])) {
            return 'poster_is_required';
        }

        // 员工海报不能为空
        if (empty($data['employee_poster'])) {
            return 'employee_poster_is_required';
        }

        // 开始时间不能大于等于结束时间
        $startTime = Util::getDayFirstSecondUnix($data['start_time']);
        $endTime = Util::getDayLastSecondUnix($data['end_time']);
        if ($startTime >= $endTime) {
            return 'start_time_eq_end_time';
        }

        // 检查奖励规则 - 不能为空
        if (empty($data['award_rule'])) {
            return 'award_rule_is_required';
        }

        return '';
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
        if (!empty($activityInfo['poster_list']) && is_array($activityInfo['poster_list'])) {
            foreach ($activityInfo['poster_list'] as $k => $p) {
                $tmpPoster = $p;
                $tmpPoster['poster_url'] = AliOSS::replaceCdnDomainForDss($p['poster_path']);
                $tmpPoster['example_url'] = !empty($p['example_path']) ? AliOSS::replaceCdnDomainForDss($p['example_path']) : '';
                if ($p['poster_ascription'] == ActivityPosterModel::POSTER_ASCRIPTION_STUDENT) {
                    $activityInfo['employee_poster'][] = $tmpPoster;
                } else {
                    $activityInfo['poster'][] = $tmpPoster;
                }
            }
            unset($activityInfo['poster_list']);
        }

        $info = WeekActivityService::formatActivityTimeStatus($activityInfo);
        $info['format_start_time'] = date("Y-m-d H:i:s", $activityInfo['start_time']);
        $info['format_end_time'] = date("Y-m-d H:i:s", $activityInfo['end_time']);
        $info['format_create_time'] = date("Y-m-d H:i:s", $activityInfo['create_time']);
        $info['enable_status_zh'] = DictConstants::get(DictConstants::ACTIVITY_ENABLE_STATUS, $activityInfo['enable_status']);
        $info['rule_type_zh'] = DictConstants::get(DictConstants::ACTIVITY_RULE_TYPE_ZH, $activityInfo['rule_type']);
        $info['employee_invite_word'] = Util::textDecode($activityInfo['employee_invite_word']);
        $info['student_invite_word'] = Util::textDecode($activityInfo['student_invite_word']);

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
     * 添加RT亲友优惠券活动
     * @param $data
     * @return bool
     * @throws RunTimeException
     */
    public static function add($data, $employeeId)
    {
        $checkAllowAdd = self::checkAllowAdd($data);
        if (!empty($checkAllowAdd)) {
            throw new RunTimeException([$checkAllowAdd]);
        }
        $time = time();
        $activityData = [
            'name' => $data['name'] ?? '',
            'app_id' => Constants::SMART_APP_ID,
            'create_time' => $time,
        ];
        $rtActivityData = [
            'name' => $activityData['name'],
            'activity_id' => 0,
            'start_time' => Util::getDayFirstSecondUnix($data['start_time']),
            'end_time' => Util::getDayLastSecondUnix($data['end_time']),
            'enable_status' => OperationActivityModel::ENABLE_STATUS_OFF,
            'rule_type' => $data['rule_type'] ?? 0,
            'employee_invite_word' => !empty($data['employee_invite_word']) ? Util::textEncode($data['employee_invite_word']) : '',
            'student_invite_word' => !empty($data['student_invite_word']) ? Util::textEncode($data['student_invite_word']) : '',
            'operator_id' => $employeeId,
            'create_time' => $time,
        ];

        $rtActivityRuleData = [
            'activity_id' => 0,
            'rule_type' => $data['rule_type'] ?? 0,
            'buy_day' => $data['buy_day'] ?? 0,
            'coupon_num' => $data['coupon_num'] ?? 0,
            'student_coupon_num' => 1,
            'coupon_id' => $data['coupon_num'] ?? '',
            'join_user_status' => $data['join_user_status'] ?? '',
            'referral_student_is_award' => $data['referral_student_is_award'] ?? 0,
            'referral_award' => !empty($data['referral_award']) ? json_encode($data['referral_award']) : json_encode([]),
            'other_data' => json_encode([]),
            'operator_id' => $employeeId,
            'create_time' => $time,
        ];

        $activityExtData = [
            'activity_id' => 0,
            'award_rule' => !empty($data['award_rule']) ? Util::textEncode($data['award_rule']) : '',
            'remark' => $data['remark'] ?? ''
        ];

        // 处理海报写入数组
        $posterArr = [];
        foreach ($data['poster'] as $_tmpPosterId) {
            $posterArr[] = [
                'poster_id' => $_tmpPosterId,
                'poster_ascription' => ActivityPosterModel::POSTER_ASCRIPTION_STUDENT,
            ];
        }
        foreach ($data['employee_poster'] as $_tmpEmployeePosterId) {
            $posterArr[] = [
                'poster_id' => $_tmpEmployeePosterId,
                'poster_ascription' => ActivityPosterModel::POSTER_ASCRIPTION_EMPLOYEE,
            ];
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        // 保存活动总表信息
        $activityId = OperationActivityModel::insertRecord($activityData);
        if (empty($activityId)) {
            $db->rollBack();
            SimpleLogger::info("RtActivityService:add insert operation_activity fail", ['data' => $activityData]);
            throw new RunTimeException(["add month activity fail"]);
        }
        // 保存配置信息
        $rtActivityData['activity_id'] = $activityId;
        $insertRes = RtActivityModel::insertRecord($rtActivityData);
        if (empty($insertRes)) {
            $db->rollBack();
            SimpleLogger::info("RtActivityService:add insert RtActivityModel fail", ['data' => $rtActivityData, 'res' => $insertRes]);
            throw new RunTimeException(["add month activity fail"]);
        }
        // 保存扩展信息
        $activityExtData['activity_id'] = $activityId;
        $activityExtId = ActivityExtModel::insertRecord($activityExtData);
        if (empty($activityExtId)) {
            $db->rollBack();
            SimpleLogger::info("RtActivityService:add insert activity_ext fail", ['data' => $activityExtData]);
            throw new RunTimeException(["add month activity fail"]);
        }

        // 保存规则
        $rtActivityRuleData['activity_id'] = $activityId;
        $res = RtActivityRuleModel::insertRecord($rtActivityRuleData);
        if (empty($res)) {
            $db->rollBack();
            SimpleLogger::info("RtActivityService:add insert activity_rule fail", ['data' => $rtActivityRuleData]);
            throw new RunTimeException(["add month activity fail"]);
        }

        // 保存海报关联关系
        $activityPosterRes = ActivityPosterModel::batchInsertActivityPoster($activityId, $posterArr);
        if (empty($activityPosterRes)) {
            $db->rollBack();
            SimpleLogger::info("RtActivityService:add batch insert activity_poster fail", ['data' => $data]);
            throw new RunTimeException(["add month activity fail"]);
        }
        $db->commit();
        return $activityId;
    }

    /**
     * 修改RT亲友优惠券活动
     * @param $data
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function edit($data, $employeeId)
    {
        $checkAllowAdd = self::checkAllowAdd($data);
        if (!empty($checkAllowAdd)) {
            throw new RunTimeException([$checkAllowAdd]);
        }
        // 检查是否存在
        if (empty($data['activity_id'])) {
            throw new RunTimeException(['record_not_found']);
        }
        $activityId = intval($data['activity_id']);
        $activityInfo = RtActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }

        // 开始处理更新数据
        $time = time();
        $activityData = [
            'name' => $data['name'] ?? '',
            'update_time' => $time,
        ];

        $rtActivityData = [
            'name' => $activityData['name'],
            'start_time' => Util::getDayFirstSecondUnix($data['start_time']),
            'end_time' => Util::getDayLastSecondUnix($data['end_time']),
            'rule_type' => $data['rule_type'] ?? 0,
            'employee_invite_word' => !empty($data['employee_invite_word']) ? Util::textEncode($data['employee_invite_word']) : '',
            'student_invite_word' => !empty($data['student_invite_word']) ? Util::textEncode($data['student_invite_word']) : '',
            'operator_id' => $employeeId,
            'update_time' => $time,
        ];

        $activityExtData = [
            'award_rule' => !empty($data['award_rule']) ? Util::textEncode($data['award_rule']) : '',
            'remark' => $data['remark'] ?? ''
        ];

        $rtActivityRuleData = [
            'rule_type' => $data['rule_type'] ?? 0,
            'buy_day' => $data['buy_day'] ?? 0,
            'coupon_num' => $data['coupon_num'] ?? 0,
            'student_coupon_num' => 1,
            'coupon_id' => $data['coupon_num'] ?? '',
            'join_user_status' => $data['join_user_status'] ?? '',
            'referral_student_is_award' => $data['referral_student_is_award'] ?? 0,
            'referral_award' => !empty($data['referral_award']) ? json_encode($data['referral_award']) : json_encode([]),
            'other_data' => json_encode([]),
            'operator_id' => $employeeId,
            'create_time' => $time,
        ];

        // 处理海报写入数组
        $posterArr = [];
        foreach ($data['poster'] as $_tmpPosterId) {
            $posterArr[] = [
                'poster_id' => $_tmpPosterId,
                'poster_ascription' => ActivityPosterModel::POSTER_ASCRIPTION_STUDENT,
            ];
        }
        foreach ($data['employee_poster'] as $_tmpEmployeePosterId) {
            $posterArr[] = [
                'poster_id' => $_tmpEmployeePosterId,
                'poster_ascription' => ActivityPosterModel::POSTER_ASCRIPTION_EMPLOYEE,
            ];
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        // 更新活动总表信息
        $res = OperationActivityModel::batchUpdateRecord($activityData, ['id' => $activityId]);
        if (is_null($res)) {
            $db->rollBack();
            SimpleLogger::info("RtActivityService:edit update operation_activity fail", ['data' => $activityData, 'activity_id' => $activityId]);
            throw new RunTimeException(["update week activity fail"]);
        }
        // 待启用状态下更新配置信息 - 启用状态或禁用状态下只可以编辑备注
        if ($activityInfo['enable_status'] == OperationActivityModel::ENABLE_STATUS_OFF) {
            $res = RtActivityModel::batchUpdateRecord($rtActivityData, ['activity_id' => $activityId]);
            if (is_null($res)) {
                $db->rollBack();
                SimpleLogger::info("RtActivityService:edit update RtActivityModel fail", ['data' => $rtActivityData, 'activity_id' => $activityId]);
                throw new RunTimeException(["update week activity fail"]);
            }

            $res = RtActivityRuleModel::batchUpdateRecord($rtActivityRuleData, ['activity_id' => $activityId]);
            if (is_null($res)) {
                $db->rollBack();
                SimpleLogger::info("RtActivityService:edit update RtActivityRuleModel fail", ['data' => $rtActivityRuleData, 'activity_id' => $activityId]);
                throw new RunTimeException(["update week activity fail"]);
            }
        } else {
            $activityExtData = [
                'remark' => $data['remark'] ?? ''
            ];
        }

        // 更新扩展信息
        $activityExtData['activity_id'] = $activityId;
        $res = ActivityExtModel::batchUpdateRecord($activityExtData, ['activity_id' => $activityId]);
        if (is_null($res)) {
            $db->rollBack();
            SimpleLogger::info("RtActivityService:edit update activity_ext fail", ['data' => $activityExtData, 'activity_id' => $activityId]);
            throw new RunTimeException(["update week activity fail"]);
        }
        // 删除原有的海报
        ActivityPosterModel::batchUpdateRecord(['is_del' => ActivityPosterModel::IS_DEL_TRUE], ['activity_id' => $activityId]);
        // 写入新的活动与海报的关系
        $activityPosterRes = ActivityPosterModel::batchInsertActivityPoster($activityId, $posterArr);
        if (empty($activityPosterRes)) {
            $db->rollBack();
            SimpleLogger::info("RtActivityService:edit batch insert activity_poster fail", ['data' => $data, 'activity_id' => $activityId]);
            throw new RunTimeException(["add week activity fail"]);
        }

        $db->commit();

        return true;
    }

    /**
     * RT亲友优惠券活动列表
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
        list($list, $total) = RtActivityModel::searchList($params, $limitOffset);

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
     * 获取RT亲友优惠券活动详情
     * @param $activityId
     * @param $isGetExt
     * @return mixed
     * @throws RunTimeException
     */
    public static function getDetailById($activityId, $isGetExt = true)
    {
        $activityInfo = RtActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        // 获取扩展信息
        $activityExt = [];
        if ($isGetExt) {
            $activityExt = ActivityExtModel::getRecord(['activity_id' => $activityId]);
        }
        // 获取活动海报
        $activityInfo['poster_list'] = PosterService::getActivityPosterList($activityInfo);
        return self::formatActivityInfo($activityInfo, $activityExt);
    }

    /**
     * 更改RT亲友优惠券活动状态
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
        $activityInfo = RtActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        if ($activityInfo['enable_status'] == $enableStatus) {
            return true;
        }
        // 活动禁用后不能再启用
        if ($activityInfo['enable_status'] == OperationActivityModel::ENABLE_STATUS_DISABLE) {
            throw new RunTimeException(['activity_disable_not_start']);
        }
        // 如果是启用 检查时间是否冲突
        if ($enableStatus == OperationActivityModel::ENABLE_STATUS_ON) {
            $startActivity = RtActivityModel::checkTimeConflict($activityInfo['start_time'], $activityInfo['end_time']);
            if (!empty($startActivity)) {
                throw new RunTimeException(['activity_time_conflict_id', '', '', array_column($startActivity, 'activity_id')]);
            }
        }

        // 修改启用状态
        $res = RtActivityModel::updateRecord($activityInfo['id'], ['enable_status' => $enableStatus, 'operator_id' => $employeeId, 'update_time' => time()]);
        if (is_null($res)) {
            throw new RunTimeException(['update_failure']);
        }

        return true;
    }
}
