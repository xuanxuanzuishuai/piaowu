<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 18:04
 */

namespace App\Services;

use App\Models\ScheduleTaskModel;
use App\Models\ScheduleTaskUserModel;

class ScheduleTaskService
{
    /**
     * @param $st
     * @param array $studentIds
     * @param array $teacherIds
     * @return array|bool
     */
    public static function addST($st, $studentIds = array(), $teacherIds = array())
    {
        $now = time();
        $stus = [];

        $stId = ScheduleTaskModel::addST($st);
        if (is_null($stId)) {
            return false;
        }
        foreach ($studentIds as $studentId) {
            $stus[] = ['status' => ScheduleTaskUserModel::STATUS_NORMAL, 'st_id' => $stId, 'user_id' => $studentId, 'user_role' => ScheduleTaskUserModel::USER_ROLE_S, 'create_time' => $now];
        }

        foreach ($teacherIds as $teacherId) {
            $stus[] = ['status' => ScheduleTaskUserModel::STATUS_NORMAL, 'st_id' => $stId, 'user_id' => $teacherId, 'user_role' => ScheduleTaskUserModel::USER_ROLE_T, 'create_time' => $now];
        }
        $ret = ScheduleTaskUserModel::addSTU($stus);
        if (is_null($ret))
            return false;

        return $stId;
    }

    /**
     * @param $stId
     * @param $userId
     * @param $userRole
     * @return bool
     */
    public static function bindUser($stId, $userId, $userRole)
    {
        $now = time();
        $insert = [];
        $st = ScheduleTaskModel::getSTDetail($stId);

        $insert[] = ['st_id' => $st['id'], 'user_id' => $userId, 'user_role' => $userRole, 'status' => ScheduleTaskUserModel::STATUS_NORMAL, 'create_time' => $now];

        $stus = ScheduleTaskUserModel::getSTUBySTIds([$stId]);
        foreach ($stus as $stu) {
            if ($stu['user_id'] == $userId && $stu['user_role'] == $userRole && $stu['status'] == ScheduleTaskUserModel::STATUS_NORMAL)
                $delIds[] = $stu['id'];
        }
        if (!empty($delIds))
            self::unBindUser($delIds);
        if (!empty($insert))
            ScheduleTaskUserModel::batchInsert($insert);
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

    /**
     * @param $params
     * @param int $page
     * @param int $count
     * @return array
     */
    public static function getSTList($params, $page = 1, $count = 20)
    {
        $stIds = $result = [];
        list($count, $sts) = ScheduleTaskModel::getSTList($params, $page, $count);
        foreach ($sts as $st) {
            $stIds[] = $st['id'];
            $result[$st['id']] = $st;
        }
        $stus = ScheduleTaskUserModel::getSTUBySTIds($stIds);
        foreach ($stus as $stu) {
            if ($stu['user_role'] == ScheduleTaskUserModel::USER_ROLE_S) {
                $result[$stu['st_id']]['students'][] = $stu;
            } else
                $result[$stu['st_id']]['teachers'][] = $stu;
        }
        return [$count, $result];
    }

    /**
     * @param $stId
     * @return array|null
     */
    public static function getSTDetail($stId)
    {
        $st = ScheduleTaskModel::getSTDetail($stId);
        if (empty($st)) {
            return null;
        }

        $stus = ScheduleTaskUserModel::getSTUBySTIds([$st['id']]);
        foreach ($stus as $stu) {
            if ($stu['user_role'] == ScheduleTaskUserModel::USER_ROLE_S) {
                $st['students'][] = $stu;
            } else
                $st['teachers'][] = $stu;
        }
        return $st;
    }

    /**
     * @param $st
     * @return array|bool
     */
    public static function checkST($st)
    {
        $now = time();
        //检查教室，时间是否冲突
        $sts = ScheduleTaskModel::getSTList(
            [
                'AND' => [
                    'classroom_id' => $st['classroom_id'],
                    'weekday' => $st['weekday'],
                    'status' => array(ScheduleTaskModel::STATUS_NORMAL, ScheduleTaskModel::STATUS_BEGIN, ScheduleTaskModel::STATUS_TEMP),
                    'start_time[<]' => $st['end_time'],
                    'end_time[>]' => $st['start_time'],

                    'or' => [
                        'expire_time' => null,
                        'expire_time[>]' => $now
                    ]
                ]
            ]
        );
        return empty($sts)?true:$sts;
    }

    /**
     * @param $sIds
     * @return array|bool
     */
    public static function checkStudent($sIds) {
        $sts = ScheduleTaskModel::getSTListByUser($sIds,ScheduleTaskUserModel::USER_ROLE_S);
        return empty($sts)?true:$sts;
    }

    /**
     * @param $tIds
     * @return array|bool
     */
    public static function checkTeacher($tIds) {
        $sts = ScheduleTaskModel::getSTListByUser($tIds,ScheduleTaskUserModel::USER_ROLE_T);
        return empty($sts)?true:$sts;
    }
}
