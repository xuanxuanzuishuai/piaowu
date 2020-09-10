<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/2/20
 * Time: 10:56 AM
 */

namespace App\Services;


use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\EmployeeModel;
use App\Models\GiftCodeModel;
use App\Models\PackageExtModel;
use App\Models\StudentModel;
use App\Libs\Valid;
use App\Models\UserWeixinModel;
use App\Models\WeChatAwardCashDealModel;
use App\Models\WeChatOpenIdListModel;
use Medoo\Medoo;

class ErpReferralService
{
    /** 转介绍事件 */
    const EVENT_TYPE_UPLOAD_POSTER = 4; // 上传分享海报
    const EVENT_TYPE_UPLOAD_POSTER_RETURN_CASH = 5; // 上传分享海报领取返现

    /**
     * 前端传值的对应关系
     */
    const EXPECT_REGISTER = 1; //注册
    const EXPECT_TRAIL_PAY = 2; //付费体验卡
    const EXPECT_YEAR_PAY = 3; //付费年卡

    /** 转介绍阶段任务 */
    const EVENT_TASK_ID_REGISTER = [1]; // 注册
    const EVENT_TASK_ID_TRIAL_PAY = [52, 2]; // 体验支付
    const EVENT_TASK_ID_PAY = [53, 3]; // 支付

    /**
     * 此属性用于和前端交互时的对应
     */
    const EVENT_TASKS = [
        self::EXPECT_REGISTER => '注册',
        self::EXPECT_TRAIL_PAY => '付费体验卡',
        self::EXPECT_YEAR_PAY => '付费年卡',
    ];

    /** 任务状态 */
    const EVENT_TASK_STATUS_COMPLETE = 2;

    /** 奖励状态状态 */
    const AWARD_STATUS_REJECTED = 0; //不发放
    const AWARD_STATUS_WAITING = 1; //待发放
    const AWARD_STATUS_APPROVAL = 2; //审核中
    const AWARD_STATUS_GIVEN = 3; //发放成功
    const AWARD_STATUS_GIVE_ING = 4; //发放中/已发放待领取
    const AWARD_STATUS_GIVE_FAIL = 5; //发放失败


    const AWARD_STATUS = [
        self:: AWARD_STATUS_REJECTED => '不发放',
        self:: AWARD_STATUS_WAITING => '待发放',
        self:: AWARD_STATUS_APPROVAL => '审核中',
        self:: AWARD_STATUS_GIVEN => '发放成功',
        self:: AWARD_STATUS_GIVE_ING => '发放中',
        self:: AWARD_STATUS_GIVE_FAIL => '发放失败'
    ];

    /** 转介绍奖励类型 */
    const AWARD_TYPE_CASH = 1; // 现金
    const AWARD_TYPE_SUBS = 2; // 订阅时长
    const AWARD_TYPE_POINT = 3; //积分
    const AWARD_TYPE_MEDAL = 4; //奖章

    /** 事件任务状态 0 未启用 1 启用 2 禁用 */
    const ERP_EVENT_TASK_STATUS_NOT_ENABLED = 0;
    const ERP_EVENT_TASK_STATUS_ENABLED = 1;
    const ERP_EVENT_TASK_STATUS_DISABLED = 2;

    //专属海报参加人数
    const PERSONAL_POSTER_ATTEND_NUM_KEY = 'personal_poster_attend_num_key';

    /**
     * @param $taskId
     * @return int
     * 前端展示用户转介绍阶段任务对应关系
     */
    public static function getTaskRelateToVue($taskId)
    {
        if (in_array($taskId, self::EVENT_TASK_ID_REGISTER)) {
            return self::EXPECT_REGISTER;
        } elseif (in_array($taskId, self::EVENT_TASK_ID_TRIAL_PAY)) {
            return self::EXPECT_TRAIL_PAY;
        } elseif (in_array($taskId, self::EVENT_TASK_ID_PAY)) {
            return self::EXPECT_YEAR_PAY;
        } else {
            return 0;
        }
    }


    /**
     * @param $taskId
     * @return string
     * 转介绍阶段任务的中文对应
     */
    public static function getTaskRelateZh($taskId)
    {
        if (in_array($taskId, self::EVENT_TASK_ID_REGISTER)) {
            return '注册';
        } elseif (in_array($taskId, self::EVENT_TASK_ID_TRIAL_PAY)) {
            return '付费体验卡';
        } elseif (in_array($taskId, self::EVENT_TASK_ID_PAY)) {
            return '付费年卡';
        } else {
            return '暂不明确';
        }
    }

    /**
     * @return int
     * 当前生效的转介绍注册任务
     */
    public static function getRegisterTaskId()
    {
        $arr = self::EVENT_TASK_ID_REGISTER;
        return reset($arr);
    }

    /**
     * @return int
     * 当前生效的体验付费任务
     */
    public static function getTrailPayTaskId()
    {
        $arr = self::EVENT_TASK_ID_TRIAL_PAY;
        return reset($arr);
    }

    /**
     * @return int
     * 当前生效的年卡付费任务
     */
    public static function getYearPayTaskId()
    {
        $arr = self::EVENT_TASK_ID_PAY;
        return reset($arr);
    }

    /**
     * @return array
     * 转介绍相关的任务
     */
    public static function getAllReferralTaskId()
    {
        return array_merge(self::EVENT_TASK_ID_REGISTER, self::EVENT_TASK_ID_TRIAL_PAY, self::EVENT_TASK_ID_PAY);
    }

    /**
     * 转介绍列表
     * @param $params
     * @return array
     * @throws RunTimeException
     */
    public static function getReferredList($params)
    {
        if (!empty($params['event_task_id'])) {
            list($includeTasks, $excludeTasks) = self::getRefEventTaskIdFilter($params['event_task_id']);
            $params['event_task_id'] = implode(',', $includeTasks);
            $params['not_event_task_id'] = implode(',', $excludeTasks);
        }
        $erp = new Erp();
        $response = $erp->referredList($params);

        if (empty($response) || $response['code'] != 0) {
            $errorCode = $response['errors'][0]['err_no'] ?? 'erp_request_error';
            throw new RunTimeException([$errorCode]);
        }
        $list = [];
        //批量获取学生信息
        $uuidList = array_merge(array_column($response['data']['records'], 'student_uuid'), array_column($response['data']['records'], 'referrer_uuid'));
        if (empty($uuidList)) {
            $studentInfoList = [];
        } else {
            $studentInfoList = array_column(StudentService::getByUuids($uuidList, ['name', 'id', 'uuid']), null, 'uuid');
        }
        foreach ($response['data']['records'] as $referred) {
            $tasks = $referred['tasks'] ?? [];
            $maxRefTask = self::findMaxRefTask($tasks);
            $maxRefTaskId = $maxRefTask['event_task_id'] ?? 0;

            $item = [
                'student_uuid' => $referred['student_uuid'],
                'student_name' => $studentInfoList[$referred['student_uuid']]['name'],
                'student_mobile_hidden' => Util::hideUserMobile($referred['student_mobile']),
                'referrer_uuid' => $referred['referrer_uuid'],
                'referrer_name' => $studentInfoList[$referred['referrer_uuid']]['name'],
                'referrer_mobile_hidden' => Util::hideUserMobile($referred['referrer_mobile']),
                'max_event_task_name' => self::getTaskRelateZh($maxRefTaskId) ?? '-',
                'register_time' => $referred['create_time'],
                'student_id' => $studentInfoList[$referred['student_uuid']]['id'],
                'referral_student_id' => $studentInfoList[$referred['referrer_uuid']]['id'],
            ];
            $list[] = $item;
        }

        return ['list' => $list, 'total_count' => $response['data']['total_count']];
    }

    /**
     * 用户转介绍列表
     * @param $uuid
     * @param $page
     * @param $count
     * @return array
     * @throws RunTimeException
     */
    public static function getUserReferredList($uuid, $page, $count)
    {
        $params = [
            'page'  => $page,
            'count' => $count,
            'referrer_uuid' => $uuid,
        ];

        $erp = new Erp();
        $response = $erp->referredList($params);

        if (empty($response) || $response['code'] != 0) {
            $errorCode = $response['errors'][0]['err_no'] ?? 'erp_request_error';
            throw new RunTimeException([$errorCode]);
        }

        $list = [];
        foreach ($response['data']['records'] as $referred) {
            $tasks = $referred['tasks'] ?? [];
            $userTasks = [];
            foreach ($tasks as $task) {
                //兼容前端展示
                $task['event_task_id'] = self::getTaskRelateToVue($task['event_task_id']);
                $userTasks[$task['event_task_id']] = [
                    'create_time' => $task['create_time'],
                    'event_task_name' => self::getTaskRelateZh($task['event_task_id']),
                ];
            }

            $item = [
                'student_uuid' => $referred['student_uuid'],
                'student_name' => $referred['student_name'],
                'student_mobile_hidden' => Util::hideUserMobile($referred['student_mobile']),
                'tasks' => $userTasks,
            ];
            $list[] = $item;
        }

        return ['list' => $list, 'total_count' => $response['data']['total_count']];
    }

    private static function findMaxRefTask($tasks)
    {
        $maxTaskIndex = -1;
        $maxTaskId = 0;
        foreach ($tasks as $idx => $task) {
            //只需要特定的转介绍任务
            if (!in_array($task['event_task_id'], self::getAllReferralTaskId())) {
                continue;
            }
            if (empty($maxTaskId) || self::refEventTaskCmp($task['event_task_id'], $maxTaskId)) {
                $maxTaskIndex = $idx;
                $maxTaskId = $task['event_task_id'];
            }
        }
        return $tasks[$maxTaskIndex];
    }

    private static function refEventTaskCmp($a, $b)
    {
        if ($a == $b) { return 0; }
        return $a > $b;
    }

    /**
     * 根据转介绍阶段筛选event_task_id
     * @param $expectTask
     * @return array
     */
    private static function getRefEventTaskIdFilter($expectTask)
    {
        $include = [];
        $exclude = [];
        switch ($expectTask) {
            case self::EXPECT_REGISTER:
                $exclude = array_merge( self::EVENT_TASK_ID_TRIAL_PAY, self::EVENT_TASK_ID_PAY);
                $include = self::EVENT_TASK_ID_REGISTER;
                break;
            case self::EXPECT_TRAIL_PAY:
                $exclude = self::EVENT_TASK_ID_PAY;
                $include = self::EVENT_TASK_ID_TRIAL_PAY;
                break;
            case self::EXPECT_YEAR_PAY:
                $include = self::EVENT_TASK_ID_PAY;
                break;
        }
        return [$include, $exclude];
    }

    /**
     * 获取奖励列表
     * @param $params
     * @return array
     * @throws RunTimeException
     */
    public static function getAwardList($params)
    {
        $erp = new Erp();
        $params['award_relate'] = Erp::AWARD_RELATE_REFERRAL;
        if (!empty($params['reviewer_name'])) {
            $params['reviewer_id'] = EmployeeModel::getRecord(['name[~]' => Util::sqlLike($params['reviewer_name'])], 'id');
        }

        if (!empty($params['event_task_id'])) {
            if ($params['event_task_id'] == self::EXPECT_TRAIL_PAY) {
                $params['event_task_id'] =  self::EVENT_TASK_ID_TRIAL_PAY;
            } else if ($params['event_task_id'] == self::EXPECT_YEAR_PAY) {
                $params['event_task_id'] =  self::EVENT_TASK_ID_PAY;
            }
        }
        $response = $erp->awardList($params);

        if (empty($response) || $response['code'] != 0) {
            $errorCode = $response['errors'][0]['err_no'] ?? 'erp_request_error';
            throw new RunTimeException([$errorCode]);
        }

        $reviewerNames = EmployeeService::getNameMap(array_column($response['data']['records'], 'reviewer_id'));
        $reviewerNames = array_column($reviewerNames, 'name', 'id');

        $list = [];
        //批量获取学生信息
        $uuidList = array_merge(array_column($response['data']['records'], 'student_uuid'), array_column($response['data']['records'], 'referrer_uuid'));
        if (empty($uuidList)) {
            $studentInfoList = [];
        } else {
            $studentInfoList = array_column(StudentService::getByUuids($uuidList, ['name', 'id', 'uuid']), null, 'uuid');
        }

        //当前奖励在微信发放的状态
        $relateUserEventTaskAwardIdArr = array_column($response['data']['records'], 'user_event_task_award_id');
        if (empty($relateUserEventTaskAwardIdArr)) {
            $relateAwardStatusArr = [];
        } else {
            $relateAwardStatusArr = array_column(WeChatAwardCashDealModel::getRecords(['user_event_task_award_id' => $relateUserEventTaskAwardIdArr], ['result_code', 'user_event_task_award_id']), NULL, 'user_event_task_award_id');
        }
        //列表用户微信关注/绑定情况
        $refereeUuidArr = array_column($response['data']['records'], 'referrer_uuid');
        if (empty($refereeUuidArr)) {
            $weChatRelateInfo = [];
        } else {
            $weChatRelateInfo = array_column(WeChatOpenIdListModel::getUuidOpenIdInfo($refereeUuidArr), NULL, 'uuid');
        }

        //关注公众号相关信息
        foreach ($response['data']['records'] as $award) {
            $subscribeStatus = $weChatRelateInfo[$award['referrer_uuid']]['subscribe_status'] ?? WeChatOpenIdListModel::UNSUBSCRIBE_WE_CHAT;
            $bindStatus = $weChatRelateInfo[$award['referrer_uuid']]['bind_status'] ?? UserWeixinModel::STATUS_DISABLE;
            $item = [
                'student_uuid' => $award['student_uuid'],
                'student_name' => $studentInfoList[$award['student_uuid']]['name'],
                'student_mobile_hidden' => Util::hideUserMobile($award['student_mobile']),
                'referrer_uuid' => $award['referrer_uuid'],
                'referrer_name' => $studentInfoList[$award['referrer_uuid']]['name'],
                'referrer_mobile_hidden' => Util::hideUserMobile($award['referrer_mobile']),
                'event_task_id' => $award['event_task_id'],
                'event_task_name' => $award['event_task_name'],
                'user_event_task_award_id' => $award['user_event_task_award_id'],
                'award_status' => $award['award_status'],
                'award_status_zh' => $award['award_status_zh'],
                'award_amount' => $award['award_type'] == self::AWARD_TYPE_CASH ? ($award['award_amount'] / 100) : $award['award_amount'],
                'fail_reason_zh' => isset($relateAwardStatusArr[$award['user_event_task_award_id']]) ? WeChatAwardCashDealModel::getWeChatErrorMsg($relateAwardStatusArr[$award['user_event_task_award_id']]['result_code']) : '',
                'award_type' => $award['award_type'],
                'create_time' => $award['create_time'],
                'review_time' => $award['review_time'],
                'reviewer_id' => $award['reviewer_id'],
                'reviewer_name' => $reviewerNames[$award['reviewer_id']] ?? '',
                'reason' => $award['reason'],
                'delay' => $award['delay'],
                'student_id' => $studentInfoList[$award['student_uuid']]['id'],
                'referral_student_id' => $studentInfoList[$award['referrer_uuid']]['id'],
                'subscribe_status' => $subscribeStatus,
                'subscribe_status_zh' => $subscribeStatus == WeChatOpenIdListModel::SUBSCRIBE_WE_CHAT ? '已关注' : '未关注',
                'bind_status' => $bindStatus,
                'bind_status_zh' => $bindStatus == UserWeixinModel::STATUS_NORMAL ? '已绑定' : '未绑定',
            ];
            $list[] = $item;
        }

        return ['list' => $list, 'total_count' => $response['data']['total_count']];
    }

    /**
     * @param $params
     * @return array
     * @throws RunTimeException
     * 社群红包奖励列表
     */
    public static function getCommunityAwardList($params)
    {
        $erp = new Erp();
        $params['award_relate'] = Erp::AWARD_RELATE_COMMUNITY;
        if (!empty($params['reviewer_name'])) {
            $params['reviewer_id'] = EmployeeModel::getRecord(['name[~]' => Util::sqlLike($params['reviewer_name'])], 'id');
        }
        if (!empty($params['event_task_id'])) {
            if ($params['event_task_id'] == self::EXPECT_TRAIL_PAY) {
                $params['event_task_id'] =  self::EVENT_TASK_ID_TRIAL_PAY;
            } else if ($params['event_task_id'] == self::EXPECT_YEAR_PAY) {
                $params['event_task_id'] =  self::EVENT_TASK_ID_PAY;
            }
        }
        $response = $erp->awardList($params);
        if (empty($response) || $response['code'] != 0) {
            $errorCode = $response['errors'][0]['err_no'] ?? 'erp_request_error';
            throw new RunTimeException([$errorCode]);
        }

        $reviewerNames = EmployeeService::getNameMap(array_column($response['data']['records'], 'reviewer_id'));
        $reviewerNames = array_column($reviewerNames, 'name', 'id');

        $list = [];
        //批量获取学生信息
        $uuidList = array_column($response['data']['records'], 'student_uuid');
        if (empty($uuidList)) {
            $studentInfoList = [];
        } else {
            $studentInfoList = array_column(StudentModel::getStudentCollectionInfo($uuidList, ['s.name', 's.id', 's.uuid', 'c.name (collection_name)']), null, 'uuid');
        }

        //当前奖励在微信发放的状态
        $relateUserEventTaskAwardIdArr = array_column($response['data']['records'], 'user_event_task_award_id');
        if (empty($relateUserEventTaskAwardIdArr)) {
            $relateAwardStatusArr = [];
        } else {
            $relateAwardStatusArr = array_column(WeChatAwardCashDealModel::getRecords(['user_event_task_award_id' => $relateUserEventTaskAwardIdArr], ['result_code', 'user_event_task_award_id']), NULL, 'user_event_task_award_id');
        }
        //列表用户微信关注/绑定情况
        $refereeUuidArr = array_column($response['data']['records'], 'student_uuid');
        if (empty($refereeUuidArr)) {
            $weChatRelateInfo = [];
        } else {
            $weChatRelateInfo = array_column(WeChatOpenIdListModel::getUuidOpenIdInfo($refereeUuidArr), NULL, 'uuid');
        }

        //关注公众号相关信息
        foreach ($response['data']['records'] as $award) {
            $subscribeStatus = $weChatRelateInfo[$award['student_uuid']]['subscribe_status'] ?? WeChatOpenIdListModel::UNSUBSCRIBE_WE_CHAT;
            $bindStatus = $weChatRelateInfo[$award['student_uuid']]['bind_status'] ?? UserWeixinModel::STATUS_DISABLE;
            $item = [
                'student_uuid' => $award['student_uuid'],
                'student_name' => $studentInfoList[$award['student_uuid']]['name'],
                'collection_name' => $studentInfoList[$award['student_uuid']]['collection_name'],
                'student_mobile_hidden' => Util::hideUserMobile($award['student_mobile']),
                'event_task_id' => $award['event_task_id'],
                'event_task_name' => $award['event_task_name'],
                'user_event_task_award_id' => $award['user_event_task_award_id'],
                'award_status' => $award['award_status'],
                'award_status_zh' => $award['award_status_zh'],
                'award_amount' => $award['award_type'] == self::AWARD_TYPE_CASH ? ($award['award_amount'] / 100) : $award['award_amount'],
                'fail_reason_zh' => isset($relateAwardStatusArr[$award['user_event_task_award_id']]) ? WeChatAwardCashDealModel::getWeChatErrorMsg($relateAwardStatusArr[$award['user_event_task_award_id']]['result_code']) : '',
                'award_type' => $award['award_type'],
                'create_time' => $award['create_time'],
                'review_time' => $award['review_time'],
                'reviewer_id' => $award['reviewer_id'],
                'reviewer_name' => $reviewerNames[$award['reviewer_id']] ?? '',
                'reason' => $award['reason'],
                'delay' => $award['delay'],
                'student_id' => $studentInfoList[$award['student_uuid']]['id'],
                'subscribe_status' => $subscribeStatus,
                'subscribe_status_zh' => $subscribeStatus == WeChatOpenIdListModel::SUBSCRIBE_WE_CHAT ? '已关注' : '未关注',
                'bind_status' => $bindStatus,
                'bind_status_zh' => $bindStatus == UserWeixinModel::STATUS_NORMAL ? '已绑定' : '未绑定',
            ];
            $list[] = $item;
        }

        return ['list' => $list, 'total_count' => $response['data']['total_count']];
    }

    /**
     * 获取用户获得的奖励列表
     * @param $uuid
     * @param $page
     * @param $count
     * @param $params
     * @return array
     * @throws RunTimeException
     */
    public static function getUserAwardList($uuid, $page, $count, $params)
    {
        $query = [
            'page'  => $page,
            'count' => $count,
            'referrer_uuid' => $uuid
        ];
        if(!empty($params['award_type'])) {
            $query['award_type'] = $params['award_type'];
        }

        $erp = new Erp();
        $response = $erp->awardList($query);

        if (empty($response) || $response['code'] != 0) {
            $errorCode = $response['errors'][0]['err_no'] ?? 'erp_request_error';
            throw new RunTimeException([$errorCode]);
        }

        $list = [];
        foreach ($response['data']['records'] as $award) {
            $item = [
                'student_uuid' => $award['student_uuid'],
                'student_name' => $award['student_name'],
                'student_mobile_hidden' => Util::hideUserMobile($award['student_mobile']),
                'event_task_id' => $award['event_task_id'],
                'event_task_name' => $award['event_task_name'],
                'user_event_task_award_id' => $award['user_event_task_award_id'],
                'award_status' => $award['award_status'],
                'award_status_zh' => $award['award_status_zh'],
                'award_amount' => ($award['award_amount'] / 100),
                'award_type' => $award['award_type'],
                'delay' => $award['delay'],
                'create_time' => $award['create_time'],
            ];
            $list[] = $item;
        }

        $cash = $response['data']['sum'][self::AWARD_TYPE_CASH];
        $sum = empty($cash) ? 0 : $cash / 100;

        return ['list' => $list, 'total_count' => $response['data']['total_count'], 'sum' => $sum];
    }

    /**
     * 更新奖励状态
     * @param $awardId
     * @param $status
     * @param $reviewerId
     * @param $reason
     * @param string $keyCode
     * @param null $eventTaskId
     * @return array|bool
     * @throws RunTimeException
     */
    public static function updateAward($awardId, $status, $reviewerId, $reason, $keyCode, $eventTaskId = NULL)
    {
        if (empty($awardId) || empty($reviewerId)) {
            throw new RunTimeException(['invalid_award_id_or_reviewer_id']);
        }
        $erp = new Erp();
        $time = time();
        //期望结果数据 批量处理支持
        $awardIdArr = array_unique(explode(',', $awardId));
        if (count($awardIdArr) > 50) {
            throw new RunTimeException(['over_max_allow_num']);
        }
        $needDealAward = [];
        if (!empty($awardIdArr)) {
            foreach ($awardIdArr as $value) {
                $needDealAward[$value] = [
                    'award_id' => $value,
                    'status' => $status,
                    'reviewer_id' => $reviewerId,
                    'reason' => $reason,
                    'review_time' => $time
                ];
            }
        }
        //实际发放结果数据 调用微信红包，
        if ($status == self::AWARD_STATUS_GIVEN) {
            //当前操作的奖励基础信息
            $awardBaseInfo = $erp->getUserAwardInfo($awardId);
            if (!empty($awardBaseInfo['data']['award_info'])) {
                $needCheckAward = array_filter($awardBaseInfo['data']['award_info'], function ($award)use($time) {
                    return ($award['award_type'] == self::AWARD_TYPE_CASH && in_array($award['status'], [self::AWARD_STATUS_WAITING, self::AWARD_STATUS_GIVE_FAIL]) && $award['create_time'] + $award['delay'] < $time);
                });
                //验证被推荐人是否已退费
                $info = self::verifyStudentStatus($eventTaskId, array_column($needCheckAward, 'res_uuid'));
                if (!empty($info)) {
                    return ['has_refund' => $info];
                }
                foreach ($awardBaseInfo['data']['award_info'] as $award) {
                    //仅现金,且未发放/已发放失败,且满足奖励延迟时间限制可调用
                    if ($award['award_type'] == self::AWARD_TYPE_CASH && in_array($award['status'], [self::AWARD_STATUS_WAITING, self::AWARD_STATUS_GIVE_FAIL]) && $award['create_time'] + $award['delay'] < $time) {
                        list($baseAwardId, $dealStatus) = CashGrantService::cashGiveOut($award['uuid'], $award['award_id'], $award['award_amount'], $reviewerId, $keyCode);
                        //重试操作无变化，无须更新erp
                        if ($award['status'] != $dealStatus) {
                            $needDealAward[$baseAwardId]['status'] = $dealStatus;
                        } else {
                            unset($needDealAward[$award['award_id']]);
                        }
                    } else {
                        unset($needDealAward[$award['award_id']]);
                        SimpleLogger::info('award data valid', $award);
                    }
                }
            }
        }

        if (empty($needDealAward)) {
            return [];
        }

        $response = $erp->batchUpdateAward($needDealAward);

        if (empty($response) || $response['code'] != 0) {
            $errorCode = $response['errors'][0]['err_no'] ?? 'erp_request_error';
            throw new RunTimeException([$errorCode]);
        }

        if(!empty($response['data'])) {
            foreach ($response['data'] as $v) {
                if (!empty($v['event_task_id'])) {
                    WeChatService::notifyUserCustomizeMessage(
                        $v['referrer_mobile'] ?: $v['receive_mobile'],
                        $v['event_task_id'],
                        [
                            'mobile' => Util::hideUserMobile($v['student_mobile']),
                            'url' => $keyCode == WeChatAwardCashDealModel::COMMUNITY_PIC_WORD ? '' : $_ENV['STUDENT_INVITED_RECORDS_URL'],
                            'awardValue' => $v['award_amount'] / 100
                        ]
                    );
                }
            }
        }

        return [];
    }

    /**
     * @param $eventTaskId
     * @param $uuidArr
     * @return string|null
     * 验证当前相关奖励的订单是否退费
     */
    public static function verifyStudentStatus($eventTaskId, $uuidArr)
    {
        if (empty($eventTaskId) || empty($uuidArr)) {
            return NULL;
        }

        $studentIdArr = StudentModel::getRecords(['uuid' => $uuidArr], 'id');

        if ($eventTaskId == self::EXPECT_TRAIL_PAY) {
            //所有的体验包id
            $packageIdArr = array_column(PackageExtModel::getPackages(['package_type' => PackageExtModel::PACKAGE_TYPE_TRIAL]), 'package_id');
        } else {
            //所有年包id
            $packageIdArr = array_column(PackageExtModel::getPackages(['package_type' => PackageExtModel::PACKAGE_TYPE_NORMAL]), 'package_id');
        }

        $existNormalBillStudentIdArr = array_column(GiftCodeModel::getRecords([
            'buyer' => $studentIdArr, 'bill_package_id' => $packageIdArr,
            'code_status' => [GiftCodeModel::CODE_STATUS_NOT_REDEEMED, GiftCodeModel::CODE_STATUS_HAS_REDEEMED]],
            ['buyer' => Medoo::raw('DISTINCT(buyer)')]), 'buyer');

        $diffArr = array_diff($studentIdArr, $existNormalBillStudentIdArr);
        if (!empty($diffArr)) {
            return implode(',', StudentModel::getRecords(['id' => $diffArr], 'mobile'));
        }
        return NULL;
    }


    /**
     * 获取用户转介绍数据
     * @param $params
     * @return array
     */
    public static function getUserReferralInfo($params)
    {
        //远程访问erp获取数据
        $erp = new Erp();
        $response = $erp->userReferralInfo($params);
        $data = [];
        if (empty($response) || $response['code'] != 0) {
            return $data;
        }
        return $response['data']['records'];
    }

    /**
     * 获取event事件列表
     * @param $eventId
     * @param $eventType
     * @return array
     */
    public static function getEventTasksList($eventId = 0, $eventType = self::EVENT_TYPE_UPLOAD_POSTER_RETURN_CASH)
    {
        //远程调用erp获取事件任务信息
        $erp = new Erp();
        $eventTaskList = $erp->eventTaskList($eventId, $eventType);

        if (empty($eventTaskList['data']) && $eventTaskList['code'] != Valid::CODE_SUCCESS) {
            return [];
        }

        //整理事件任务数据
        $tasksData = [];
        array_map(function ($task) use (&$tasksData) {
            if (is_array($task['tasks'])) {
                $tasksData = array_merge($tasksData, $task['tasks']);
            }
        }, $eventTaskList['data']);

        if (empty($tasksData)) {
            return [];
        }

        $data = [];
        foreach ($tasksData as $ek => &$ev) {
            if ($ev['status'] == self::ERP_EVENT_TASK_STATUS_ENABLED) {
                $ev['condition'] = json_decode($ev['condition'], true);
                $data[] = $tasksData[$ek];
            }
        }

        return $data;
    }

    /**
     * @return array
     * 专属海报播报的数据
     */
    public static function broadDataRelate()
    {
        $erp = new Erp();
        $needShowInfo = [
            ['type' => self::EXPECT_REGISTER, 'page' => 1, 'count' => 25],
            ['type' => self::EXPECT_TRAIL_PAY, 'page' => 1, 'count' => 25],
            ['type' => self::EXPECT_YEAR_PAY, 'page' => 1, 'count' => 30]
        ];
        $returnInfo = [];
        foreach ($needShowInfo as $value) {
            list($includeTasks, $excludeTasks) = self::getRefEventTaskIdFilter($value['type']);
            $params['event_task_id'] = $includeTasks;
            $params['page'] = $value['page'];
            $params['count'] = $value['count'];
            $response = $erp->awardList($params);
            $studentInfoList = array_column(StudentModel::getRecords(['uuid' => array_column($response['data']['records'], 'referrer_uuid')], ['name', 'uuid']), null, 'uuid');
            $returnInfo[$value['type']] = array_map(function ($item) use($studentInfoList) {
                if (in_array($item['award_type'], [self::AWARD_TYPE_CASH, self::AWARD_TYPE_SUBS])) {
                    if ($item['award_type'] == self::AWARD_TYPE_CASH) {
                        $str = $item['award_amount'] / 100 . '元';
                    } else {
                        $str = $item['award_amount'] . '天时长';
                    }
                    return ['referee_name' => self::dealShowName($studentInfoList[$item['referrer_uuid']]['name']), 'award_info' => $str, 'award_id' => $item['user_event_task_award_id']];
                }
            }, $response['data']['records']);

        }
        $joinNum = RedisDB::getConn()->get(self::PERSONAL_POSTER_ATTEND_NUM_KEY) ?: DictConstants::get(DictConstants::PERSONAL_POSTER, 'initial_num');
        return [$returnInfo, $joinNum];
    }

    /**
     * @param $name
     * @return string
     * 隐藏展示的用户名
     */
    private static function dealShowName($name)
    {
        $count = mb_strlen($name);
        if ($count == 1) {
            return $name . '*';
        } elseif ($count == 2) {
            return mb_substr($name, 0, 1) . '*';
        } elseif ($count > 2) {
            return  mb_substr($name, 0, 1) . str_repeat('*', $count - 2) . mb_substr($name, -1, 1) ;
        }
    }
}