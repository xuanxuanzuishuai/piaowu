<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-24
 * Time: 10:17
 */

namespace App\Services;


use App\Models\ScheduleTaskUserModel;
use App\Models\ScheduleUserModel;

class ScheduleUserService
{
    /**
     * @param $scheduleIds
     * @param $SUs
     * @return bool
     */
    public static function bindSUs($scheduleIds,$SUs) {
        $now = time();
        foreach ($scheduleIds as $scheduleId) {
            foreach ($SUs as $role => $userIds) {
                foreach ($userIds as $userId) {
                    $userStatus = $role == ScheduleTaskUserModel::USER_ROLE_S ? ScheduleUserModel::STUDENT_STATUS_BOOK: ScheduleUserModel::TEACHER_STATUS_SET;
                    $sus[] = ['status' => ScheduleUserModel::STATUS_NORMAL, 'schedule_id' => $scheduleId, 'user_id' => $userId, 'user_role' => $role, 'create_time' => $now,'user_status'=>$userStatus];
                }
            }
        }
        if (!empty($sus)) {
            $ret = ScheduleUserModel::insertSUs($sus);
            if (is_null($ret))
                return false;
        }
        return true;
    }

    /**
     * @param $suIds
     * @return int|null
     */
    public static function unBindUser($suIds) {
        return ScheduleUserModel::updateSUStatus($suIds,ScheduleUserModel::STATUS_CANCEL);
    }

    /**
     * @param $userIds
     * @param $userRols
     * @param $startTime
     * @param $endTime
     * @param $orgId
     * @return array|bool
     */
    public static function checkScheduleUser($userIds,$userRols,$startTime,$endTime,$orgId) {
        $sus = ScheduleUserModel::checkScheduleUser($userIds,$userRols,$startTime,$endTime,$orgId);
        return empty($sus) ? true :$sus;
    }
}