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
use App\Libs\RC4;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\ActivityExtModel;
use App\Models\ActivityPosterModel;
use App\Models\Dss\DssEmployeeModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpStudentAccountModel;
use App\Models\Erp\ErpStudentCouponV1Model;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\OperationActivityModel;
use App\Models\ParamMapModel;
use App\Models\RtActivityModel;
use App\Models\RtActivityRuleModel;
use App\Models\StudentReferralStudentStatisticsModel;
use App\Models\RtCouponReceiveRecordModel;
use Medoo\Medoo;
use App\Libs\Erp;
use App\Models\EmployeeActivityModel;
use App\Models\TemplatePosterModel;


class RtActivityService
{
    const GRANT_WAY_USER   = 3; //优惠券发放方式-用户领取

    const GRANT_PURPOSE_JILI    = 3; //发放目的-激励

    const DEFAULT_NUM = 1;//默认领取数量

    //活动状态
    const ACTIVITY_NORMAL      = 1; //正常
    const ACTIVITY_NOT_STARTED = 2; //未开始
    const ACTIVITY_IS_END      = 3; //已结束

    //领取状态
    const COUPON_IS_SUCCESS   = 1; //领取成功
    const COUPON_IS_NOT_ALLOW = 2; //用户不符合条件
    const COUPON_IS_FINISH    = 3; //代金券已领完

    //转介绍rt参与标识
    const RT_CHANNEL_REFERRAL     = 1; //参与
    const NOT_RT_CHANNEL_REFERRAL = 0; //未参与


    public static $timeArray = [
        'day' => '天',
        'hour' => '小时',
        'minute' => '分钟',
    ];

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
                if ($p['poster_ascription'] == ActivityPosterModel::POSTER_ASCRIPTION_EMPLOYEE) {
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
            'start_time' => strtotime($data['start_time']),
            'end_time' => strtotime($data['end_time']),
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
            'coupon_id' => $data['coupon_id'] ?? '',
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
                'start_time' => strtotime($data['start_time']),
                'end_time' => strtotime($data['end_time']),
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
                'coupon_id' => $data['coupon_id'] ?? '',
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
                'year_card_sale_url' => $data['year_card_sale_url'] ?? '',
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
            ActivityPosterModel::delActivityPoster($activityId);
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
            $startActivity = RtActivityModel::checkTimeConflict($activityInfo);
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
     * @param $enableStatus
     * @return array
     */
    public static function getRtActivityList($ruleType, $activityName, $page, $count, $enableStatus = [])
    {
        $limit = [($page - 1) * $count, $count];
        $fields = ['activity_id', 'name', 'start_time', 'end_time', 'enable_status'];
        $where = ['name' => $activityName, 'rule_type' => $ruleType];
        if (!empty($enableStatus)) {
            $where['enable_status'] = $enableStatus;
        }
        list($activityList) = RtActivityModel::searchList($where, $limit, [], $fields);
        $time = time();
        foreach ($activityList as $k => $v) {
            if (OperationActivityModel::checkActivityEnableStatusOn($v, $time)) {
                unset($activityList[$k]);
                array_unshift($activityList, $v);
                break;
            }
        }
        return array_values($activityList);
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
     * rt亲友优惠券活动
     * 获取领取Rt学员优惠券的转介绍学员数量
     * 当前Rt学员优惠券未过期+当前阶段为付费体验课（有可用的优惠券，未付费的学员）
     * @return array
     */
    public static function rtActivityCouponUserList($params)
    {
        $returnData = [
            "total_count" => 0,
        ];
        $assistantIds = $params['assistant_ids'] ?? "";
        if (empty($assistantIds)) {
            return $returnData;
        }
        $assistantIds = explode(',', $assistantIds);
        $returnData['total_count'] = StudentReferralStudentStatisticsModel::getAssistantStudentCount($assistantIds);
        return $returnData;
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
        if ($params['status']) {
            switch ($params['status']) {
                case 1:   // 未领取
                    $params['record_status'] = RtCouponReceiveRecordModel::NOT_REVEIVED_STATUS;
                    break;
                case 2:   // 已领取(未使用)
                    $params['record_status'] = RtCouponReceiveRecordModel::REVEIVED_STATUS;
                    $params['coupon_status'] = ErpStudentCouponV1Model::STATUS_UNUSE;
                    break;
                case 3:   // 已使用
                    $params['record_status'] = RtCouponReceiveRecordModel::REVEIVED_STATUS;
                    $params['coupon_status'] = ErpStudentCouponV1Model::STATUS_USED;
                    break;
                case 4:   // 已过期
                    $params['record_status'] = RtCouponReceiveRecordModel::REVEIVED_STATUS;
                    $params['coupon_status'] = ErpStudentCouponV1Model::STATUS_EXPIRE;
                    break;
                case 5:   // 已作废
                    $params['record_status'] = RtCouponReceiveRecordModel::REVEIVED_STATUS;
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
    
    /**
     * 格式化数据格式
     * @param $data
     * @return mixed
     */
    private static function formatListData($data)
    {
        $data['rule_type_zh'] = DictConstants::get(DictConstants::ACTIVITY_RULE_TYPE_ZH, $data['rule_type']);
        $data['dss_share_employee_id_name'] = $data['dss_share_employee_id'] . '/' . $data['dss_share_employee_name'];
        $data['dss_share_employee_id_name'] == '/' && $data['dss_share_employee_id_name'] = '-';
        if ($data['rule_type'] == RtActivityModel::ACTIVITY_RULE_TYPE_SHEQUN) {   // 社群活动
            $data['dss_belong_employee_id_name'] = $data['dss_assistant_id'] . '/' . $data['dss_assistant_name'];
        }
        if ($data['rule_type'] == RtActivityModel::ACTIVITY_RULE_TYPE_KEGUAN) {   // 课管活动
            $data['dss_belong_employee_id_name'] = $data['dss_course_manage_id'] . '/' . $data['dss_course_manage_name'];
        }
        $data['dss_belong_employee_id_name'] == '/' && $data['dss_belong_employee_id_name'] = '-';
        $data['has_review_course_zh'] = DssStudentModel::CURRENT_PROGRESS[$data['has_review_course']];
        $data['create_date'] = $data['create_time'] ? date('Y-m-d H:i:s', $data['create_time']) : '-';
        $data['expired_start_date'] = $data['expired_start_time'] ? date('Y-m-d H:i:s', $data['expired_start_time']) : '-';
        $data['expired_end_date'] = $data['expired_end_time'] ? date('Y-m-d H:i:s', $data['expired_end_time']) : '-';
        $data['order_id'] = $data['order_id'] ? $data['order_id'] : '-';
        $data['status_zh'] = '-';
        if ($data['record_status'] == RtCouponReceiveRecordModel::NOT_REVEIVED_STATUS) {
            $data['status_zh'] = '未领取';
        }
        if ($data['record_status'] == RtCouponReceiveRecordModel::REVEIVED_STATUS) {
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



    /**
     * 推荐人首页
     * @param $request
     * @return array
     * @throws RunTimeException
     */
    public static function inviteIndex($request)
    {
        $time = time();
        $status = self::ACTIVITY_NORMAL;
        $activityId = $request['activity_id'];
        //校验活动
        $activity = RtActivityModel::getRecord(['activity_id'   => $activityId, 'enable_status' => RtActivityModel::ENABLED_STATUS]);
        if ($activity['start_time'] > $time) {
            $status = self::ACTIVITY_NOT_STARTED;
        }
        if (empty($activity) || $activity['end_time'] < $time) {
            $status = self::ACTIVITY_IS_END;
        }
        $timeRemaining = $activity['end_time'] - $time;
        $activityExt   = ActivityExtModel::getRecord(['activity_id' => $activityId]);
        $data = [
            'status'         => $status,
            'activity_ext'   => $activityExt['award_rule'] ?? '',
            'time_remaining' => $timeRemaining,
        ];
        return $data;
    }


    /**
     * 获取邀请记录
     * @param $request
     * @return array
     * @throws RunTimeException
     */
    public static function getInviteRecord($request)
    {
        $activityId   = $request['activity_id'];
        $uid          = $request['student_id'];
        $inviteRecord = [];
        //获取剩余优惠券数量
        $remainNums = self::remainCouponNums($activityId, $uid);
        //查询邀请记录
        $conds       = [
            'activity_id' => $activityId,
            'invite_uid'  => $uid,
            'LIMIT'       => 50,
            'ORDER'       => ['id' => 'DESC'],
        ];
        $inviteArray = RtCouponReceiveRecordModel::getRecords($conds, ['invite_uid', 'receive_uid','student_coupon_id']);
        if (!empty($inviteArray)) {
            $receiveUids  = array_column($inviteArray, 'receive_uid');//被推荐人
            $studentInfos = DssStudentModel::getRecords(['id' => $receiveUids], ['id', 'mobile']);
            $studentArray = !empty($studentInfos) ? array_column($studentInfos, 'mobile', 'id') : [];
            //查询转介绍记录
            $referralInfos = StudentReferralStudentStatisticsModel::getRecords(['student_id'  => $receiveUids, 'activity_id' => $activityId], ['student_id', 'create_time']);
            $referralArray = !empty($referralInfos) ? array_column($referralInfos, 'create_time', 'student_id') : [];
            //优惠券使用状态
            $studentCouponId = array_filter(array_column($inviteArray, 'student_coupon_id'));
            $couponLists = [];
            if (!empty($studentCouponId)) {
                $coupon = (new Erp())->getStudentCouponPageById($studentCouponId);
                $couponLists = $coupon['data']['list'] ?? [];
                $couponLists = !empty($couponLists) ? array_column($couponLists, 'student_coupon_status_zh', 'student_coupon_id') : [];
            }
            foreach ($inviteArray as $val) {
                if (!isset($studentArray[$val['receive_uid']]) || !isset($referralArray[$val['receive_uid']])) {
                    continue;
                }
                $inviteRecord[] = [
                    'mobile'      => Util::hideUserMobile($studentArray[$val['receive_uid']]),
                    'create_time' => date('Y-m-d H:i:s', $referralArray[$val['receive_uid']]),
                    'statusTxt'   => $couponLists[$val['student_coupon_id']] ?? '未领取',
                ];
            }
        }
        $data = [
            'remain_nums'   => $remainNums,
            'invate_record' => $inviteRecord,
        ];
        return $data;
    }

    /**
     * 被邀人首页
     * @param $request
     * @return array|int[]
     * @throws RunTimeException
     */
    public static function invitedIndex($request)
    {
        $status = self::ACTIVITY_NORMAL;
        $activityId = $request['activity_id'];
        //获取学生信息
        $inviteInfo = DssStudentModel::getRecord(['id' => $request['invite_uid']], ['thumb']);
        if (empty($inviteInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        //校验活动
        $time = time();
        $activity = RtActivityModel::getRecord(['activity_id'   => $activityId, 'enable_status' => RtActivityModel::ENABLED_STATUS]);
        if ($activity['start_time'] > $time) {
            $status = self::ACTIVITY_NOT_STARTED;
        }
        if (empty($activity) || $activity['end_time'] < $time) {
            $status = self::ACTIVITY_IS_END;
        }
        $timeRemaining = $activity['end_time'] - $time;
        $activityExt   = ActivityExtModel::getRecord(['activity_id' => $activityId]);

        $data = [
            'status'         => $status,
            'invite_avatar'  => $inviteInfo['thumb'] ? AliOSS::replaceCdnDomainForDss($inviteInfo['thumb']) : AliOSS::replaceCdnDomainForDss(DictConstants::get(DictConstants::STUDENT_DEFAULT_INFO, 'default_thumb')),
            'activity_ext'   => $activityExt['award_rule'] ?? '',
            'time_remaining' => $timeRemaining,
        ];
        return $data;
    }
    /**
     * 校验邀请人资格
     * @param $activityId
     * @param $studentId
     * @return bool
     * @throws RunTimeException
     */
    public static function checkAllowSend($activityId, $studentId)
    {
        $activity = RtActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($activity)) {
            throw new RunTimeException(['record_not_found']);
        }
        $time = time();
        if ($activity['end_time'] < time()) {
            return false;
        }
        //查询活动规则
        $rule = RtActivityRuleModel::getRecord(['activity_id' => $activity['activity_id']], ['buy_day', 'coupon_num']);
        if (empty($rule)) {
            return false;
        }
        $buyDayTime = $rule['buy_day'] * Util::TIMESTAMP_ONEDAY; //购买天数
        //首次购买年卡记录
        $data = DssGiftCodeModel::getUserFirstBuyInfo($studentId);
        if (empty($data)) {
            return false;
        }
        $data = end($data);
        $duration = Util::formatDurationDay($data['valid_units'], $data['valid_num']);
        $days = DictConstants::get(DictConstants::OFFICIAL_DURATION_CONFIG, 'year_days');
        if ($duration < $days) {
            return false;
        }
        switch ($activity['rule_type']) {
            case RtActivityModel::COMMUNITY_TYPE_STATUS:
                //首次购买年卡X天内
                if ($time > $data['buy_time'] + $buyDayTime) {
                    return false;
                }
                break;
            case RtActivityModel::MANAGEMENT_TYPE_STATUS:
                //首次购买年卡(含)X天以上
                if ($time < $data['buy_time'] + $buyDayTime) {
                    return false;
                }
                break;
        }
        //激活码未激活|已退费
        if ($data['code_status'] != DssGiftCodeModel::CODE_STATUS_HAS_REDEEMED) {
            return false;
        }
        //激活码已过期
        $expireTimes = $duration * Util::TIMESTAMP_ONEDAY + $data['be_active_time'];
        if ($time > $expireTimes) {
            return false;
        }
        return $activity;
    }

    /**
     * 校验被请人资格
     * @param $activityId
     * @param $student
     * @param $isNew
     * @return false|mixed
     */
    public static function checkAllowReceive($activityId, $student, $isNew)
    {
        $time = time();
        //校验活动
        $activity = RtActivityModel::getRecord(['activity_id' => $activityId, 'enable_status' => RtActivityModel::ENABLED_STATUS]);
        if (empty($activity) || $activity['start_time'] > $time || $activity['end_time'] < $time) {
            return false;
        }
        //校验用户是否有资格领取
        $rule = RtActivityRuleModel::info(['activity_id' => $activity['activity_id']], ['join_user_status','coupon_id']);
        if (empty($rule['join_user_status'])) {
            return false;
        }
        $joinStatusArray = explode(',', $rule['join_user_status']);
        //未注册
        if (in_array(RtActivityRuleModel::NOT_REGISTER, $joinStatusArray)) {
            $isRegister = $isNew && ($student['create_time'] + Util::TIMESTAMP_1H > $time);
        }
        //已注册
        if (in_array(RtActivityRuleModel::IS_REGISTER, $joinStatusArray)) {
            $notRegister = true;
            //已有年卡
            if ($student['has_review_course'] == DssStudentModel::STATUS_BUY_NORMAL_COURSE) {
                $notRegister = false;
            }
            //已有转介绍关系
            $referral = StudentReferralStudentStatisticsModel::getRecord(['student_id' => $student['id']]);
            if (!empty($referral)) {
                $notRegister = false;
            }
        }
        if (!$isRegister && !$notRegister) {
            return false;
        }
        $activity['coupon_id'] = $rule['coupon_id'];
        return $activity;
    }

    /**
     * 获取海报
     * @param $request
     * @return array|int[]
     * @throws RunTimeException
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public static function getPoster($request)
    {
        //学生获取海报前 校验是否有资格
        if ($request['type'] == RtActivityModel::ACTIVITY_RULE_TYPE_STUDENT) {
            //获取学生信息
            $studentUid = DssStudentModel::getRecord(['id' => $request['student_id']], 'id');
            if (empty($studentUid)) {
                throw new RunTimeException(['record_not_found']);
            }
            $posterAscription = ActivityPosterModel::POSTER_ASCRIPTION_STUDENT;
            $activity = self::checkAllowSend($request['activity_id'], $request['student_id']);
            if (!$activity) {
                return ['status' => self::ACTIVITY_NOT_STARTED];
            }
            //被邀人首页链接
            $scheme = DictConstants::get(DictConstants::RT_ACTIVITY_INDEX, 'rt_invited');
        } else {
            $posterAscription = ActivityPosterModel::POSTER_ASCRIPTION_EMPLOYEE;
            switch ($request['type']) {
                case RtActivityModel::ACTIVITY_RULE_TYPE_SHEQUN: //社群
                    $conds    = [
                        'rule_type' => RtActivityModel::ACTIVITY_RULE_TYPE_SHEQUN,
                        'start_time[<=]' => time(),
                        'end_time[>=]'   => time(),
                        'enable_status'  => RtActivityModel::ENABLED_STATUS,
                    ];
                    $activity = RtActivityModel::getRecord($conds);
                    if (empty($activity)) {
                        return [];
                    }
                    break;
                case RtActivityModel::ACTIVITY_RULE_TYPE_KEGUAN: //课管
                    //校验用户是否符合参与条件
                    $activity = RtActivityModel::getRecord(['activity_id' => $request['activity_id'],'rule_type' => RtActivityModel::ACTIVITY_RULE_TYPE_KEGUAN]);
                    if (empty($activity)) {
                        throw new RunTimeException(['record_not_found']);
                    }
                    break;
                default:
                    throw new RunTimeException(['record_not_found']);
            }
            //邀请人首页链接
            $scheme = DictConstants::get(DictConstants::RT_ACTIVITY_INDEX, 'rt_invite');
        }
        if (empty($scheme)) {
            throw new RunTimeException(['record_not_found']);
        }
        //获取buy_type
        $rule = RtActivityRuleModel::getRecord(['activity_id' => $activity['activity_id']], ['buy_day']);
        if (empty($rule)) {
            throw new RunTimeException(['record_not_found']);
        }
        //课管计划任务调用直接返回
        if ($request['type'] == RtActivityModel::ACTIVITY_RULE_TYPE_KEGUAN && empty($request['employee_id'])) {
            return ['buy_day' => $rule['buy_day']];
        }

        //查询海报
        $conds    = [
            'activity_id'       => $activity['activity_id'],
            'status'            => ActivityPosterModel::NORMAL_STATUS,
            'is_del'            => ActivityPosterModel::IS_DEL_FALSE,
            'poster_ascription' => $posterAscription
        ];
        $posterId = ActivityPosterModel::getRecord($conds, 'poster_id');
        if (empty($posterId)) {
            throw new RunTimeException(['record_not_found']);
        }
        //获取海报
        $templatePosterPath = TemplatePosterModel::getRecord(['id' => $posterId, 'status' => TemplatePosterModel::NORMAL_STATUS], ['poster_path','poster_id']);
        if (empty($templatePosterPath)) {
            throw new RunTimeException(['record_not_found']);
        }
        $posterUrl = AliOSS::replaceCdnDomainForDss($templatePosterPath['poster_path']);
        list($imageWidth, $imageHeight) = getimagesize($posterUrl);
        if (empty($imageHeight) || empty($imageWidth)) {
            throw new RunTimeException(['data_error']);
        }
        //学生调用海报记录 ParamMap
        if ($request['type'] == RtActivityModel::ACTIVITY_RULE_TYPE_STUDENT) {
            $paramMapId = self::addParamMap($request['student_id'], $activity['activity_id'], $templatePosterPath['poster_id']);
            if (!$paramMapId) {
                throw new RunTimeException(['record_not_found']);
            }
        }
        $params = [
            'activity_id'   => $activity['activity_id'],
            'employee_id'   => $request['employee_id'],
            'employee_uuid' => $request['employee_uuid'],
            'invite_uid'    => $studentUid ?? '',
            'param_id'      => $paramMapId ?? ''
        ];
        $qrURL = $scheme . '?' . http_build_query(array_filter($params));
        $userQrPath = ReferralActivityService::commonActivityQr($qrURL);
        $posterConfig = PosterService::getPosterConfig();
        $posterInfo = ReferralActivityService::genEmployeePoster(
            $templatePosterPath['poster_path'],
            $imageWidth,
            $imageHeight,
            $userQrPath,
            $posterConfig['QR_WIDTH'],
            $posterConfig['QR_HEIGHT'],
            $posterConfig['QR_X'],
            $posterConfig['QR_Y']
        );
        unset($posterInfo['qr_url']);
        if ($request['type'] == RtActivityModel::ACTIVITY_RULE_TYPE_STUDENT) {
            $posterInfo['invite_word'] = $activity['student_invite_word'];
        } else {
            $posterInfo['invite_word'] = $activity['employee_invite_word'];
        }
        $posterInfo['status']  = self::ACTIVITY_NORMAL;
        $posterInfo['buy_day'] = $rule['buy_day'];
        $posterInfo['activity_id'] = $activity['activity_id'];
        return $posterInfo;
    }

    /**
     * ParamMap插入
     * @param $userId
     * @param $activityId
     * @param $posterId
     * @return int|mixed|string|null
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public static function addParamMap($userId, $activityId, $posterId)
    {
        $ticket = RC4::encrypt($_ENV['COOKIE_SECURITY_KEY'], ParamMapModel::TYPE_STUDENT . "_" . $userId);
        $paramInfo = [
            'r' => $ticket,
            'c' => self::getRtChannel(),
            'a' => $activityId,
            'p' => $posterId,
            'user_current_status' => DssStudentModel::STATUS_BUY_NORMAL_COURSE
        ];
        $paramInfo = json_encode($paramInfo);
        $result = ParamMapModel::getRecord(['param_info' => $paramInfo], ['id']);
        if (!empty($result)) {
            return $result['id'];
        }
        $insert = [
            'app_id'      => Constants::SMART_APP_ID,
            'type'        => ParamMapModel::TYPE_STUDENT,
            'user_id'     => $userId,
            'param_info'  => $paramInfo,
            'create_time' => time(),
        ];
        ParamMapModel::insertRecord($insert);
        $result = ParamMapModel::getRecord(['param_info' => $paramInfo], ['id']);
        return $result['id'];
    }


    /**
     * 发放优惠券
     * @param $request
     * @return bool
     * @throws RunTimeException
     */
    public static function receiveCoupon($request)
    {
        $redisKey = sprintf('receive_coupon_%s', $request['student_id']);
        $repeat   = Util::preventRepeatSubmit($redisKey);
        if (!$repeat) {
            throw new RunTimeException(['request_repeat']);
        }
        $activityId = $request['activity_id'];
        //学生信息
        $student = DssStudentModel::getRecord(['id' => $request['student_id']], ['id','uuid','has_review_course','create_time']);
        if (empty($student)) {
            throw new RunTimeException(['record_not_found']);
        }
        //邀请人信息
        $inviteInfo = DssStudentModel::getRecord(['id' => $request['invite_uid']], ['id','uuid']);
        if (empty($inviteInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        //查询是否已领取 若已领取跳转对应页
        $record = RtCouponReceiveRecordModel::info(['activity_id' => $activityId, 'receive_uid' => $student['id']], ['id','status']);
        if (!empty($record) && $record['status'] == RtCouponReceiveRecordModel::REVEIVED_STATUS) {
            return ['status' => self::COUPON_IS_SUCCESS];
        }
        if (!empty($record) && $record['status'] == RtCouponReceiveRecordModel::NOT_REVEIVED_STATUS) {
            return ['status' => self::COUPON_IS_FINISH];
        }
        //校验是否有资格领取
        $activity = self::checkAllowReceive($activityId, $student, $request['is_new']);
        if (!$activity) {
            return ['status' => self::COUPON_IS_NOT_ALLOW];
        }
        //查询员工信息
        switch ($activity['rule_type']) {
            case RtActivityModel::ACTIVITY_RULE_TYPE_SHEQUN: //社群
            case RtActivityModel::ACTIVITY_RULE_TYPE_KEGUAN: //课管
                $employeeUuid = DssEmployeeModel::getRecord(['id' => $request['employee_id']], 'uuid');
                break;
            default:
                throw new RunTimeException(['record_not_found']);
        }
        if ($employeeUuid != $request['employee_uuid']) {
            throw new RunTimeException(['record_not_found']);
        }
        //符合领取条件-后置操作1.0元领取体验卡 2.订单映射记录 3.领取优惠券 3.记录邀请关系
        /**
         * 0元领取体验卡
         */
        $packageId = PayServices::getPackageIDByParameterPkg(PayServices::PACKAGE_0);
        $remark = DictConstants::get(DictConstants::RT_ACTIVITY_INDEX, 'rt_activity_remark');
        $res = ErpOrderV1Service::createZeroOrder($packageId, $student, $remark);
        if (empty($res)) {
            throw new RunTimeException(['create_bill_error']);
        }
        /**
         * 订单映射记录
         */
        $res = BillMapService::mapDataRecord(['param_id' => $request['param_id']], $res['order_id'], $student['id']);
        if (empty($res)) {
            SimpleLogger::error('insert bill_map error ', ['order_id' => $res['order_id']]);
        }
        /**
         * 领取优惠券
         */
        //查询剩余优惠券数量
        $remainNums = self::remainCouponNums($activityId, $request['invite_uid']);
        $status = RtCouponReceiveRecordModel::NOT_REVEIVED_STATUS; //未领取
        if ($remainNums > 0) {
            $params = [
                'grant_purpose' => self::GRANT_PURPOSE_JILI,
                'grant_way'     => self::GRANT_WAY_USER,
                'num'           => self::DEFAULT_NUM,
                'coupon_id'     => $activity['coupon_id'],
                'uuids'         => [$student['uuid']],
                'grant_time'    => time(),
                'remark'        => '',
            ];
            $res = (new Erp())->grantCoupon($params);
            if (!empty($res)) {
                $res    = end($res);
                $status = RtCouponReceiveRecordModel::REVEIVED_STATUS; //已领取
            }
        }
        $insertData = [
            'activity_id'       => $activityId,
            'rule_type'         => $activity['rule_type'],
            'employee_uid'      => $request['employee_id'],
            'employee_uuid'     => $request['employee_uuid'],
            'invite_uid'        => $inviteInfo['id'],
            'invite_uuid'       => $inviteInfo['uuid'],
            'receive_uid'       => $student['id'],
            'receive_uuid'      => $student['uuid'],
            'coupon_id'         => $res['coupon_id'] ?? '',
            'student_coupon_id' => $res['student_coupon_id'] ?? '',
            'status'            => $status,
            'create_time'       => time(),
        ];
        $res = RtCouponReceiveRecordModel::insertRecord($insertData);
        if (empty($res)) {
            SimpleLogger::error('insert rt_coupon_receive_record error ', $insertData);
        }
        return ['status' => $remainNums > 0 ? self::COUPON_IS_SUCCESS : self::COUPON_IS_FINISH];
    }

    /**
     * 查询当前活动 邀请人剩余优惠券数量
     * @param $activityId
     * @param $invateUuid
     * @return int|mixed|number
     * @throws RunTimeException
     */
    public static function remainCouponNums($activityId, $inviteUid)
    {
        //查询邀请人优惠券总数
        $conds['activity_id'] = $activityId;
        $couponNum = RtActivityRuleModel::getRecord($conds, 'coupon_num');
        if (empty($couponNum)) {
            throw new RunTimeException(['record_not_found']);
        }
        //查询已发放优惠券总数
        $conds['invite_uid'] = $inviteUid;
        $conds['status']     = RtCouponReceiveRecordModel::REVEIVED_STATUS;
        $grantNum            = RtCouponReceiveRecordModel::getCount($conds);
        return ($couponNum - $grantNum) < 0 ? 0 : $couponNum - $grantNum;
    }

    /**
     * 领取优惠券后-获取页面信息
     * @param $request
     * @return array
     * @throws RunTimeException
     */
    public static function couponCollecte($request)
    {
        $activity = RtActivityModel::getRecord(['activity_id' => $request['activity_id']], ['year_card_sale_url']);
        if (empty($activity)) {
            throw new RunTimeException(['record_not_found']);
        }
        $student = DssStudentModel::getRecord(['id' => $request['student_id']], ['id','assistant_id']);
        if (empty($student['assistant_id'])) {
            return ['scheme' => $activity['year_card_sale_url']];
        }
        $employee = DssEmployeeModel::getRecord(['id' => $student['assistant_id']], ['wx_qr', 'wx_num']);
        //优惠券有效期获取
        $studentCouponId = RtCouponReceiveRecordModel::info(['activity_id' => $request['activity_id'], 'receive_uid' => $student['id']], 'student_coupon_id');
        if (!empty($studentCouponId)) {
            $endTime = ErpStudentCouponV1Model::getRecord(['id' => $studentCouponId], 'expired_end_time');
        }
        $data = [
            'wx_num'   => $employee['wx_num'] ?? '',
            'wx_qr'    => !empty($employee['wx_qr']) ? AliOSS::replaceCdnDomainForDss($employee['wx_qr']) : '',
            'scheme'   => $activity['year_card_sale_url'],
            'end_date' => !empty($endTime) ? date('Y-m-d H:i:s', $endTime) : ''
        ];
        return $data;
    }

    /**
     * 批量获取转介绍人数
     * @param $request
     * @return array|null
     * @throws RunTimeException
     */
    public static function getReferralNums($request)
    {
        if (!is_array($request['referee_ids'])) {
            throw new RunTimeException(['referee_ids_is_error']);
        }
        if (count($request['referee_ids']) > 500) {
            throw new RunTimeException(['referee_ids_over_quantity']);
        }
        $refereeIds = implode(',', $request['referee_ids']);
        $data = StudentReferralStudentStatisticsModel::getReferralCount($refereeIds, $request['activity_id']);

        return $data;
    }

    /**
     * 获取rt渠道
     * @return int|mixed
     */
    public static function getRtChannel()
    {
        $data = DictConstants::getSet(DictConstants::RT_CHANNEL_CONFIG);
        return $data['rt_channel_v1'] ?? 0;
    }

    /**
     * 修改RT亲友优惠券活动年卡连接和备注
     * @param $data
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function editActivityRemark($data, $employeeId)
    {
        // 检查是否存在
        if (empty($data['activity_id'])) {
            throw new RunTimeException(['record_not_found']);
        }
        $activityId = intval($data['activity_id']);
        $activityInfo = RtActivityModel::getRecord(['activity_id' => $activityId]);
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }

        $activityExtData = [
            'remark' => $data['remark'] ?? ''
        ];
        if ($activityInfo['enable_status'] == OperationActivityModel::ENABLE_STATUS_ON) {
            // 启用状态只可以编辑备注和年卡连接
            $rtActivityData = [
                'year_card_sale_url' => $data['year_card_sale_url'] ?? '',
            ];
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        // 更新活动配置
        if (!empty($rtActivityData)) {
            $res = RtActivityModel::batchUpdateRecord($rtActivityData, ['activity_id' => $activityId]);
            if (is_null($res)) {
                $db->rollBack();
                SimpleLogger::info("RtActivityService:edit update RtActivityModel fail", ['data' => $rtActivityData, 'activity_id' => $activityId]);
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

        $db->commit();

        return true;
    }
}
