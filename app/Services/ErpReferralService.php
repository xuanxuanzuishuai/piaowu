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
use App\Libs\Util;

class ErpReferralService
{
    const EVENT_TASK_ID_REGISTER = 1;
    const EVENT_TASK_ID_TRIAL_PAY = 2;
    const EVENT_TASK_ID_PAY = 3;

    const EVENT_TASKS = [
        self::EVENT_TASK_ID_REGISTER => '注册',
        self::EVENT_TASK_ID_TRIAL_PAY => '付费体验课',
        self::EVENT_TASK_ID_PAY => '付费正式课',
    ];

    const EVENT_TASK_STATUS_COMPLETE = 2;

    const AWARD_STATUS_REJECTED = 0;
    const AWARD_STATUS_WAITING = 1;
    const AWARD_STATUS_APPROVAL = 2;
    const AWARD_STATUS_GIVEN = 3;

    const AWARD_STATUS = [
        self:: AWARD_STATUS_REJECTED => '废除',
        self:: AWARD_STATUS_WAITING => '待发放',
        self:: AWARD_STATUS_APPROVAL => '审核中',
        self:: AWARD_STATUS_GIVEN => '已发放',
    ];

    /**
     * 转介绍列表
     * @param $params
     * @return array
     * @throws RunTimeException
     */
    public static function getReferredList($params)
    {
        $erp = new Erp();
        $response = $erp->referredList($params);

        if (empty($response) || $response['code'] != 0) {
            throw new RunTimeException(['erp_request_error']);
        }

        $list = [];
        foreach ($response['data']['records'] as $referred) {
            $tasks = $referred['tasks'] ?? [];
            $maxRefTask = self::findMaxRefTask($tasks);
            $maxRefTaskId = $maxRefTask['event_task_id'] ?? 0;

            $item = [
                'student_uuid' => $referred['student_uuid'],
                'student_name' => $referred['student_name'],
                'student_mobile_hidden' => Util::hideUserMobile($referred['student_mobile']),
                'referrer_uuid' => $referred['referrer_uuid'],
                'referrer_name' => $referred['referrer_name'],
                'referrer_mobile_hidden' => Util::hideUserMobile($referred['referrer_mobile']),
                'max_event_task_name' => self::EVENT_TASKS[$maxRefTaskId] ?? '-',
                'register_time' => $referred['create_time'],
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
            throw new RunTimeException(['erp_request_error']);
        }

        $reviewerNames = EmployeeService::getNameMap(array_column($response['data']['records'], 'reviewer_id'));
        $reviewerNames = array_column($reviewerNames, 'name', 'id');

        $list = [];
        foreach ($response['data']['records'] as $referred) {
            $item = [
                'student_uuid' => $referred['student_uuid'],
                'student_name' => $referred['student_name'],
                'student_mobile_hidden' => Util::hideUserMobile($referred['student_mobile']),
                'referrer_uuid' => $referred['referrer_uuid'],
                'referrer_name' => $referred['referrer_name'],
                'referrer_mobile_hidden' => Util::hideUserMobile($referred['referrer_mobile']),
                'event_task_id' => $referred['event_task_id'],
                'event_task_name' => $referred['event_task_name'],
                'award_status' => $referred['award_status'],
                'award_status_zh' => $referred['award_status_zh'],
                'award_amount' => $referred['award_amount'],
                'award_type' => $referred['award_type'],
                'create_time' => $referred['create_time'],
                'review_time' => $referred['review_time'],
                'reviewer_id' => $referred['reviewer_id'],
                'reviewer_name' => $reviewerNames[$referred['reviewer_id']] ?? '',
                'reason' => $referred['reason'],
            ];
            $list[] = $item;
        }

        return ['list' => $list, 'total_count' => $response['data']['total_count']];
    }

}