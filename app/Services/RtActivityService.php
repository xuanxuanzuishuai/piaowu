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
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpStudentAccountModel;
use App\Models\Erp\ErpStudentCouponV1Model;
use App\Models\OperationActivityModel;
use App\Models\RtActivityModel;
use App\Models\RtActivityRuleModel;
use App\Models\RtCouponReceiveRecordModel;
use Medoo\Medoo;

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

        // 结束时间不能小于等于当前时间
        if ($endTime <= time()) {
            return 'end_time_eq_time';
        }

        if ($data['buy_day'] >= 1000 || $data['buy_day'] <= 0) {
            return 'buy_day_error';
        }
        if ($data['coupon_num'] >= 1000 || $data['coupon_num'] <= 0) {
            return 'coupon_num_error';
        }

        if (!empty($data['referral_student_is_award'])) {
            if (empty($data['referral_award']['delay_day']) || $data['referral_award']['delay_day'] >= 100 || $data['referral_award']['delay_day'] <= 0) {
                return 'referral_award_error';
            }
            if (empty($data['referral_award']['amount']) || $data['referral_award']['amount'] >= 100000 || $data['referral_award']['amount'] <= 0) {
                return 'amount_error';
            }
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
     * @param $ruleInfo
     * @return mixed
     */
    public static function formatActivityInfo($activityInfo, $extInfo, $ruleInfo = [])
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

        if (!empty($ruleInfo)) {
            $ruleInfo['referral_award'] = json_decode($ruleInfo['referral_award'], true);
            $info = array_merge($ruleInfo, $info);
        }
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
            'year_card_sale_url' => $data['year_card_sale_url'] ?? '',
            'employee_invite_word' => !empty($data['employee_invite_word']) ? Util::textEncode($data['employee_invite_word']) : '',
            'student_invite_word' => !empty($data['student_invite_word']) ? Util::textEncode($data['student_invite_word']) : '',
            'operator_id' => $employeeId,
            'create_time' => $time,
        ];

        $referralAward = [
            "delay" => !empty($data['referral_award']['delay_day']) ? $data['referral_award']['delay_day']*Util::TIMESTAMP_ONEDAY : 0,
            "amount" => !empty($data['referral_award']['amount']) ? $data['referral_award']['amount'] : 0,
            "account_sub_type" => ErpStudentAccountModel::SUB_TYPE_GOLD_LEAF,
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
            'referral_award' => json_encode($referralAward),
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

        // 待启用状态下更新配置信息
        if ($activityInfo['enable_status'] == OperationActivityModel::ENABLE_STATUS_OFF) {
            $activityData = [
                'name' => $data['name'] ?? '',
                'update_time' => $time,
            ];

            $rtActivityData = [
                'name' => $activityData['name'],
                'start_time' => Util::getDayFirstSecondUnix($data['start_time']),
                'end_time' => Util::getDayLastSecondUnix($data['end_time']),
                'rule_type' => $data['rule_type'] ?? 0,
                'year_card_sale_url' => $data['year_card_sale_url'] ?? '',
                'employee_invite_word' => !empty($data['employee_invite_word']) ? Util::textEncode($data['employee_invite_word']) : '',
                'student_invite_word' => !empty($data['student_invite_word']) ? Util::textEncode($data['student_invite_word']) : '',
                'operator_id' => $employeeId,
                'update_time' => $time,
            ];

            $referralAward = [
                "delay" => !empty($data['referral_award']['delay_day']) ? $data['referral_award']['delay_day']*Util::TIMESTAMP_ONEDAY : 0,
                "amount" => !empty($data['referral_award']['amount']) ? $data['referral_award']['amount'] : 0,
                "account_sub_type" => ErpStudentAccountModel::SUB_TYPE_GOLD_LEAF,
            ];
            $rtActivityRuleData = [
                'rule_type' => $data['rule_type'] ?? 0,
                'buy_day' => $data['buy_day'] ?? 0,
                'coupon_num' => $data['coupon_num'] ?? 0,
                'student_coupon_num' => 1,
                'coupon_id' => $data['coupon_num'] ?? '',
                'join_user_status' => $data['join_user_status'] ?? '',
                'referral_student_is_award' => $data['referral_student_is_award'] ?? 0,
                'referral_award' => json_encode($referralAward),
                'other_data' => json_encode([]),
                'operator_id' => $employeeId,
                'create_time' => $time,
            ];

            $activityExtData = [
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
        } elseif ($activityInfo['enable_status'] == OperationActivityModel::ENABLE_STATUS_ON) {
            // 启用状态只可以编辑备注和年卡连接
            $activityExtData = [
                'remark' => $data['remark'] ?? ''
            ];
            $rtActivityData = [
                'rule_type' => $data['rule_type'] ?? 0,
            ];
        } else {
            // 禁用状态下只可以编辑备注
            $activityExtData = [
                'remark' => $data['remark'] ?? ''
            ];
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        // 更新活动总表信息
        if (!empty($activityData)) {
            $res = OperationActivityModel::batchUpdateRecord($activityData, ['id' => $activityId]);
            if (is_null($res)) {
                $db->rollBack();
                SimpleLogger::info("RtActivityService:edit update operation_activity fail", ['data' => $activityData, 'activity_id' => $activityId]);
                throw new RunTimeException(["update week activity fail"]);
            }
        }

        // 更新活动配置
        if (!empty($rtActivityData)) {
            $res = RtActivityModel::batchUpdateRecord($rtActivityData, ['activity_id' => $activityId]);
            if (is_null($res)) {
                $db->rollBack();
                SimpleLogger::info("RtActivityService:edit update RtActivityModel fail", ['data' => $rtActivityData, 'activity_id' => $activityId]);
                throw new RunTimeException(["update week activity fail"]);
            }
        }

        // 更新活动规则
        if (!empty($rtActivityRuleData)) {
            $res = RtActivityRuleModel::batchUpdateRecord($rtActivityRuleData, ['activity_id' => $activityId]);
            if (is_null($res)) {
                $db->rollBack();
                SimpleLogger::info("RtActivityService:edit update RtActivityRuleModel fail", ['data' => $rtActivityRuleData, 'activity_id' => $activityId]);
                throw new RunTimeException(["update week activity fail"]);
            }
        }

        // 更新扩展信息
        if (!empty($activityExtData)) {
            $activityExtData['activity_id'] = $activityId;
            $res = ActivityExtModel::batchUpdateRecord($activityExtData, ['activity_id' => $activityId]);
            if (is_null($res)) {
                $db->rollBack();
                SimpleLogger::info("RtActivityService:edit update activity_ext fail", ['data' => $activityExtData, 'activity_id' => $activityId]);
                throw new RunTimeException(["update week activity fail"]);
            }
        }

        // 更新海报
        if (!empty($posterArr)) {
            // 删除原有的海报
            ActivityPosterModel::batchUpdateRecord(['is_del' => ActivityPosterModel::IS_DEL_TRUE], ['activity_id' => $activityId]);
            // 写入新的活动与海报的关系
            $activityPosterRes = ActivityPosterModel::batchInsertActivityPoster($activityId, $posterArr);
            if (empty($activityPosterRes)) {
                $db->rollBack();
                SimpleLogger::info("RtActivityService:edit batch insert activity_poster fail", ['data' => $data, 'activity_id' => $activityId]);
                throw new RunTimeException(["add week activity fail"]);
            }
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

        // 获取奖励规则
        $ruleInfo = RtActivityRuleModel::getRecord(['activity_id' => $activityId]);
        return self::formatActivityInfo($activityInfo, $activityExt, $ruleInfo);
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

    /**
     * 获取RT优惠券活动名称 第一条是当前正在使用的活动
     * @param $ruleType
     * @param $activityName
     * @param $page
     * @param $count
     * @return array
     */
    public static function getRtActivityList($ruleType, $activityName, $page, $count)
    {
        $limit = [($page - 1) * $count, $count];
        $fields = ['activity_id', 'name', 'start_time', 'end_time', 'enable_status'];
        list($activityList) = RtActivityModel::searchList(['name' => $activityName, 'rule_type' => $ruleType], $limit, [], $fields);
        $time = time();
        foreach ($activityList as $k => $v) {
            if (OperationActivityModel::checkActivityEnableStatusOn($v, $time)) {
                array_unshift($activityList, $v);
                unset($activityList[$k]);
            }
        }
        return $activityList;
    }

    /**
     * 批量获取RT亲友优惠券活动id详情
     * @param $ruleType
     * @param $activityName
     * @param $page
     * @param $count
     * @return array
     */
    public static function getRtActivityInfo($activityIds)
    {
        $returnActivityList = [];
        $activityIdArr = explode(',', $activityIds);
        if (empty($activityIdArr)) {
            return $returnActivityList;
        }
        $activityList = RtActivityModel::getRecords(['activity_id' => $activityIdArr]);
        if (empty($activityList)) {
            return $returnActivityList;
        }
        $activityRuleList = RtActivityRuleModel::getRecords(['activity_id' => $activityIdArr]);
        $activityRuleList = array_column($activityRuleList, null, 'activity_id');

        foreach ($activityList as $item) {
            $rule = $activityRuleList[$item['activity_id']] ?? [];
            $returnActivityList[$item['activity_id']] = self::formatActivityInfo($item, [], $rule);
        }
        return $returnActivityList;
    }

    /**
     * rt亲友优惠券活动
     * 获取活动已绑定过的优惠券批次Id
     * @return array
     */
    public static function getRtActivityCouponIdList()
    {
        $couponIdArr = [];
        $activityList = RtActivityRuleModel::getRecords([], ['coupon_id' => Medoo::raw('DISTINCT coupon_id')]);
        if (empty($activityList)) {
            return $couponIdArr;
        }
        foreach ($activityList as $item) {
            $couponIdArr = array_merge($couponIdArr, explode(',', $item['coupon_id']));
        }
        return ['list' => array_unique($couponIdArr)];
    }
    
    /**
     * 活动明细
     * $params = [
     *    'activity_id' => 3,
     *    'rule_type' => 0,
     *    'dss_employee_id' => 10805,
     *    'dss_employee_name' => 'test',
     *    'erp_employee_id' => '10805',
     *    'erp_employee_name' => 'test',
     *    'invite_uid' => 283130,
     *    'invite_mobile' => '18239051727',
     *    'receive_uid' => 283130,
     *    'receive_mobile' => '18239051727',
     *    'status' => 0,
     *    'coupon_status' => 0,
     *    'create_time_start' => 0,
     *    'create_time_end' => 111,
     *    'order_id' => '',
     *    'has_review_course' => 0,
     * ];
     * @param $params
     * @param $page
     * @param $limit
     * @return array
     */
    public static function activityInfoList($params, $page, $limit)
    {
        //$params = [
        //    'activity_id' => 3,
        //    'rule_type' => 0,
        //    'dss_employee_id' => 10805,
        //    'dss_employee_name' => 'test',
        //    'erp_employee_id' => '10805',
        //    'erp_employee_name' => 'test',
        //    'invite_uid' => 283130,
        //    'invite_mobile' => '18239051727',
        //    'receive_uid' => 283130,
        //    'receive_mobile' => '18239051727',
        //    'status' => 0,
        //    'coupon_status' => 0,
        //    'create_time_start' => 0,
        //    'create_time_end' => 111,
        //    'order_id' => '',
        //    'has_review_course' => 0,
        //];
        if ($params['status']) {
            switch ($params['status']) {
                case 1:   // 未领取
                    $params['status'] = RtCouponReceiveRecordModel::NOT_REVEIVED_STATUS;
                    break;
                case 2:   // 已领取(未使用)
                    $params['status'] = RtCouponReceiveRecordModel::REVEIVED_STATUS;
                    $params['coupon_status'] = ErpStudentCouponV1Model::STATUS_UNUSE;
                    break;
                case 3:   // 已使用
                    $params['status'] = RtCouponReceiveRecordModel::REVEIVED_STATUS;
                    $params['coupon_status'] = ErpStudentCouponV1Model::STATUS_USED;
                    break;
                case 4:   // 已过期
                    $params['status'] = RtCouponReceiveRecordModel::REVEIVED_STATUS;
                    $params['coupon_status'] = ErpStudentCouponV1Model::STATUS_EXPIRE;
                    break;
                case 5:   // 已作废
                    $params['status'] = RtCouponReceiveRecordModel::REVEIVED_STATUS;
                    $params['coupon_status'] = ErpStudentCouponV1Model::STATUS_ABANDONED;
                    break;
            }
        }
        $count = RtCouponReceiveRecordModel::getActivityInfoList($params, 'count');
        $totalCount = $count[0]['num'] ?? 0;
        $offset = ($page - 1) * $limit;
        $offset = $offset < 0 ? 0 : $offset;
        list($result, $where) = RtCouponReceiveRecordModel::getActivityInfoList($params, 'list', $offset, $limit);
        $result = array_map(function ($v) {
            return self::formatListData($v);
        }, $result);
        $returnData = ['total_count' => $totalCount, 'list' => $result, 'where' => $where];
        return $returnData;
    }
    
    private static function formatListData($data)
    {
        $data['rule_type_zh'] = DictConstants::get(DictConstants::ACTIVITY_RULE_TYPE_ZH, $data['rule_type']);
        $data['dss_employee_id_name'] = $data['dss_employee_id'] . '/' . $data['dss_employee_name'];
        $data['erp_employee_id_name'] = $data['dss_employee_id'] . '/' . $data['dss_employee_name'];
        if ($data['rule_type'] == RtActivityModel::ACTIVITY_RULE_TYPE_SHEQUN) {   // 社群活动
            $data['erp_employee_id_name'] = '-';
        }
        if ($data['rule_type'] == RtActivityModel::ACTIVITY_RULE_TYPE_KEGUAN) {   // 课管活动
            $data['dss_employee_id_name'] = '-';
        }
        $data['has_review_course_zh'] = DssStudentModel::CURRENT_PROGRESS[$data['has_review_course']];
        $data['create_date'] = $data['create_time'] ? date('Y-m-d H:i:s', $data['create_time']) : '-';
        $data['expired_start_date'] = $data['expired_start_time'] ? date('Y-m-d H:i:s', $data['expired_start_time']) : '-';
        $data['expired_end_date'] = $data['expired_end_time'] ? date('Y-m-d H:i:s', $data['expired_end_time']) : '-';
        $data['order_id'] = $data['order_id'] ? $data['order_id'] : '-';
        $data['status_zh'] = '-';
        if ($data['status'] == RtCouponReceiveRecordModel::NOT_REVEIVED_STATUS) {
            $data['status_zh'] = '未领取';
        }
        if ($data['status'] == RtCouponReceiveRecordModel::REVEIVED_STATUS) {
            if ($data['coupon_status'] == ErpStudentCouponV1Model::STATUS_UNUSE) {
                $data['status_zh'] = '已领取(未使用)';
            }
            if ($data['coupon_status'] == ErpStudentCouponV1Model::STATUS_USED) {
                $data['status_zh'] = '已使用';
            }
            if ($data['coupon_status'] == ErpStudentCouponV1Model::STATUS_EXPIRE) {
                $data['status_zh'] = '已过期';
            }
            if ($data['coupon_status'] == ErpStudentCouponV1Model::STATUS_ABANDONED) {
                $data['status_zh'] = '已作废';
            }
        }
        return $data;
    }
}
