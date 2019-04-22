<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-19
 * Time: 19:18
 */

namespace App\Services;


use App\Libs\SimpleLogger;
use App\Models\ScheduleTaskUserModel;

class ScheduleTaskUserService
{
    /**
     * @param $stId
     * @param $STUs
     * @return bool
     */
    public static function bindSTUs($stId,$STUs) {
        $now = time();
        foreach($STUs as $role => $userIds) {
            foreach($userIds as $userId) {
                $stus[] = ['status' => ScheduleTaskUserModel::STATUS_NORMAL, 'st_id' => $stId, 'user_id' => $userId, 'user_role' => $role, 'create_time' => $now];
            }
        }
        if(!empty($stus)) {
            $ret = ScheduleTaskUserModel::addSTU($stus);
            if (is_null($ret))
                return false;
        }
        return true;
    }

    /**
     * @param $stuIds
     * @return int|null
     */
    public static function unBindUser($stuIds)
    {
        return ScheduleTaskUserModel::updateSTUStatus($stuIds, ScheduleTaskUserModel::STATUS_CANCEL);
    }
}