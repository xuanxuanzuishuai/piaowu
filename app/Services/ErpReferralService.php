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
    const REF_EVENT_TASK_ID_REGISTER = 1;
    const REF_EVENT_TASK_ID_TRIAL_PAY = 2;
    const REF_EVENT_TASK_ID_PAY = 3;

    const REF_EVENT_TASK_INFO = [
        self::REF_EVENT_TASK_ID_REGISTER => '注册',
        self::REF_EVENT_TASK_ID_TRIAL_PAY => '付费体验课',
        self::REF_EVENT_TASK_ID_PAY => '付费正式课',
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
            list($maxEventTaskId, $registerTime) = self::findTaskInfo($tasks);

            $item = [
                'student_uuid' => $referred['student_uuid'],
                'student_name' => $referred['student_name'],
                'student_mobile_hidden' => Util::hideUserMobile($referred['student_mobile']),
                'referrer_uuid' => $referred['referrer_uuid'],
                'referrer_name' => $referred['referrer_name'],
                'referrer_mobile_hidden' => Util::hideUserMobile($referred['referrer_mobile']),
                'max_event_task_name' => self::REF_EVENT_TASK_INFO[$maxEventTaskId] ?? '-',
                'register_time' => $registerTime,
            ];
            $list[] = $item;
        }

        return ['list' => $list, 'total_count' => $response['data']['total_count']];
    }

    private static function findTaskInfo($tasks)
    {
        $registerTime = 0;
        $maxEventTaskId = 0;
        foreach ($tasks as $task) {
            if ($task['event_task_id'] == self::REF_EVENT_TASK_ID_REGISTER) {
                $registerTime = $task['create_time'];
            }
            if (empty($maxEventTaskId)) {
                $maxEventTaskId = $task['event_task_id'];
            } else {
                if (self::refEventTaskCmp($maxEventTaskId, $task['event_task_id']) > 0) {
                    $maxEventTaskId = $task['event_task_id'];
                }
            }
        }
        return [$maxEventTaskId, $registerTime];
    }

    private static function refEventTaskCmp($a, $b)
    {
        if ($a == $b) { return 0; }
        return $a > $b;
    }

}