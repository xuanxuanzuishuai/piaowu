<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-19
 * Time: 19:18
 */

namespace App\Services;

use App\Controllers\Schedule\ScheduleTaskUser;
use App\Libs\Constants;
use App\Models\ScheduleTaskUserModel;

class ScheduleTaskUserService
{
    /**
     * @param $stIds
     * @param $STUs
     * @return bool
     */
    public static function bindSTUs($stIds,$STUs) {
        $now = time();
        foreach($stIds as $stId) {
            foreach ($STUs as $role => $userIds) {
                foreach ($userIds as $userId) {
                    $stus[] = ['status' => ScheduleTaskUserModel::STATUS_NORMAL, 'st_id' => $stId, 'user_id' => $userId, 'user_role' => $role, 'create_time' => $now];
                }
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

    public static function formatSTU($stu) {
        $stu['stu_user_role'] = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_USER_ROLE,$stu['user_role']);
        $stu['stu_status'] = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_TASK_USER_STATUS,$stu['status']);
        return $stu;
    }
}