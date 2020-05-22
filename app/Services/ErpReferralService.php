<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/2/20
 * Time: 10:56 AM
 */

namespace App\Services;


use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\UserWeixinModel;
use App\Models\WeChatAwardCashDealModel;
use App\Models\WeChatOpenIdListModel;

class ErpReferralService
{
    /** 转介绍事件 */
    const EVENT_TYPE_UPLOAD_POSTER = 4; // 上传分享海报


    /** 转介绍阶段任务 */
    const EVENT_TASK_ID_REGISTER = 1; // 注册
    const EVENT_TASK_ID_TRIAL_PAY = 2; // 体验支付
    const EVENT_TASK_ID_PAY = 3; // 支付

    const EVENT_TASKS = [
        self::EVENT_TASK_ID_REGISTER => '注册',
        self::EVENT_TASK_ID_TRIAL_PAY => '付费体验卡',
        self::EVENT_TASK_ID_PAY => '付费年卡',
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
                'max_event_task_name' => self::EVENT_TASKS[$maxRefTaskId] ?? '-',
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
                $userTasks[$task['event_task_id']] = [
                    'create_time' => $task['create_time'],
                    'event_task_name' => self::EVENT_TASKS[$task['event_task_id']],
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
     * @param $taskId
     * @return array
     */
    private static function getRefEventTaskIdFilter($taskId)
    {
        $include = [$taskId];
        $exclude = [];
        switch ($taskId) {
            case self::EVENT_TASK_ID_REGISTER:
                $exclude[] = self::EVENT_TASK_ID_TRIAL_PAY;
                $exclude[] = self::EVENT_TASK_ID_PAY;
                break;
            case self::EVENT_TASK_ID_TRIAL_PAY:
                $exclude[] = self::EVENT_TASK_ID_PAY;
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
        $studentInfoList = array_column(StudentService::getByUuids($uuidList, ['name', 'id', 'uuid']), null, 'uuid');
        //当前奖励在微信发放的状态
        $relateUserEventTaskAwardIdArr = array_column($response['data']['records'], 'user_event_task_award_id');
        $relateAwardStatusArr = array_column(WeChatAwardCashDealModel::getRecords(['user_event_task_award_id' => $relateUserEventTaskAwardIdArr], ['result_code', 'user_event_task_award_id']), NULL, 'user_event_task_award_id');
        //列表用户微信关注/绑定情况
        $refereeUuidArr = array_column($response['data']['records'], 'referrer_uuid');
        $weChatRelateInfo = array_column(WeChatOpenIdListModel::getUuidOpenIdInfo($refereeUuidArr), NULL, 'uuid');

        //关注公众号相关信息
        foreach ($response['data']['records'] as $award) {
            $subscribeStatus = $weChatRelateInfo[$award['referrer_uuid']]['subsribe_status'] ?? WeChatOpenIdListModel::UNSUBSCRIBE_WE_CHAT;
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
     * @return array|bool
     * @throws RunTimeException
     */
    public static function updateAward($awardId, $status, $reviewerId, $reason)
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
                foreach ($awardBaseInfo['data']['award_info'] as $award) {
                    //仅现金,且未发放/已发放失败,且满足奖励延迟时间限制可调用
                    if ($award['award_type'] == self::AWARD_TYPE_CASH && in_array($award['status'], [self::AWARD_STATUS_WAITING, self::AWARD_STATUS_GIVE_FAIL]) && $award['create_time'] + $award['delay'] < $time) {
                        list($baseAwardId, $dealStatus) = CashGrantService::cashGiveOut($award['uuid'], $award['award_id'], $award['award_amount'], $reviewerId);
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
                        $v['referrer_mobile'],
                        $v['event_task_id'],
                        [
                            'mobile' => Util::hideUserMobile($v['student_mobile']),
                            'url' => $_ENV['STUDENT_INVITED_RECORDS_URL']
                        ]
                    );
                }
            }
        }

        return [];
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
}