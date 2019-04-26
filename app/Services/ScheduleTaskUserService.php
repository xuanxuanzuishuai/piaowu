<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-19
 * Time: 19:18
 */

namespace App\Services;

use App\Libs\Constants;
use App\Models\ScheduleTaskUserModel;

class ScheduleTaskUserService
{
    /**
     * @param $stIds
     * @param $STUs
     * @return bool
     */
    public static function bindSTUs($stIds, $STUs)
    {
        $now = time();
        foreach ($stIds as $stId) {
            foreach ($STUs as $role => $userIds) {
                foreach ($userIds as $userId => $price) {
                    $stus[] = ['status' => ScheduleTaskUserModel::STATUS_NORMAL, 'st_id' => $stId, 'user_id' => $userId, 'price'=>$price*100,'user_role' => $role, 'create_time' => $now];
                }
            }
        }
        if (!empty($stus)) {
            $ret = ScheduleTaskUserModel::addSTU($stus);
            if (is_null($ret))
                return false;
        }
        return true;
    }

    /**
     * @param $stuIds
     * @param $stId
     * @return int|null
     */
    public static function unBindUser($stuIds,$stId)
    {
        return ScheduleTaskUserModel::updateSTUStatus(['id'=>$stuIds,'st_id'=>$stId], ScheduleTaskUserModel::STATUS_CANCEL);
    }

    /**
     * @param $stu
     * @return mixed
     */
    public static function formatSTU($stu)
    {
        $stu['stu_user_role'] = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_USER_ROLE, $stu['user_role']);
        $stu['stu_status'] = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_TASK_USER_STATUS, $stu['status']);
        return $stu;
    }
}