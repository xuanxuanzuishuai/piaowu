<?php
namespace App\Services;

use App\Models\StudentInviteModel;
use Elasticsearch\Common\Exceptions\RuntimeException;

class UserRefereeService
{
    const EVENT_TYPE_REGISTER = 'event_type_register';

    /**
     * 转介绍奖励入口
     * @param $eventType
     * @param $params
     */
    public static function refereeAwardDeal($eventType, $params)
    {
        if ($eventType == self::EVENT_TYPE_REGISTER) {
            self::registerDeal($params['student_id'] ?? NULL, $params['qr_ticket'] ?? NULL, $params['app_id'] ?? NULL, $params['employee_id'] ?? NULL, $params['activity_id'] ?? NULL);
        }
    }

    public static function registerDeal($studentId, $qrTicket, $appId, $employeeId = NULL, $activityId = NULL)
    {
        if (empty($studentId) || empty($qrTicket) || empty($appId)) {
            throw new RuntimeException(['id is required for exists_source']);
        }
        //绑定转介绍关系
    }
}