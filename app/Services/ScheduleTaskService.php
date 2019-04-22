<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 18:04
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Dict;
use App\Libs\SimpleLogger;
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
        $stus = [];
        $stId = ScheduleTaskModel::addST($st);
        if (is_null($stId)) {
            return false;
        }
        $stus[ScheduleTaskUserModel::USER_ROLE_S] = $studentIds;
        $stus[ScheduleTaskUserModel::USER_ROLE_T] = $teacherIds;

        if (!empty($stus)) {
            $res = ScheduleTaskUserService::bindSTUs($stId, $stus);
            if ($res == false) {
                return $res;
            }

        }
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

        $insert[] = ['st_id' => $stId, 'user_id' => $userId, 'user_role' => $userRole, 'status' => ScheduleTaskUserModel::STATUS_NORMAL, 'create_time' => $now];

        $stus = ScheduleTaskUserModel::getSTUBySTIds([$stId]);
        foreach ($stus as $stu) {
            if ($stu['user_id'] == $userId && $stu['user_role'] == $userRole && $stu['status'] == ScheduleTaskUserModel::STATUS_NORMAL)
                $delIds[] = $stu['id'];
        }
        if (!empty($delIds))
            ScheduleTaskUserService::unBindUser($delIds);
        if (!empty($insert))
            ScheduleTaskUserModel::batchInsert($insert);
        return true;
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
            $st = self::formatST($st);
            $stIds[] = $st['id'];
            $result[$st['id']] = $st;
        }
        $stus = ScheduleTaskUserModel::getSTUBySTIds($stIds);
        foreach ($stus as $stu) {
            if ($stu['user_role'] == ScheduleTaskUserModel::USER_ROLE_S) {
                $result[$stu['st_id']]['students']++;
            } else
                $result[$stu['st_id']]['teachers']++;
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
        $st = self::formatST($st);
        SimpleLogger::error('ssss',$st);







        $stus = ScheduleTaskUserModel::getSTUBySTIds([$st['id']]);
        foreach ($stus as $stu) {
            $stu = ScheduleTaskUserService::formatSTU($stu);
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
        $sts = ScheduleTaskModel::checkSTList($st);
        return empty($sts) ? true : $sts;
    }

    /**
     * @param $sIds
     * @param $start_time
     * @param $end_time
     * @param $weekday
     * @param null $orgSTId
     * @return array|bool
     */
    public static function checkStudent($sIds, $start_time, $end_time, $weekday, $orgSTId = null)
    {
        $sts = ScheduleTaskModel::getSTListByUser($sIds, ScheduleTaskUserModel::USER_ROLE_S, $start_time, $end_time, $weekday, $orgSTId);
        return empty($sts) ? true : $sts;
    }

    /**
     * @param $tIds
     * @param $start_time
     * @param $end_time
     * @param $weekday
     * @param null $orgSTId
     * @return array|bool
     */
    public static function checkTeacher($tIds, $start_time, $end_time, $weekday, $orgSTId = null)
    {
        $sts = ScheduleTaskModel::getSTListByUser($tIds, ScheduleTaskUserModel::USER_ROLE_T, $start_time, $end_time, $weekday, $orgSTId);
        return empty($sts) ? true : $sts;
    }

    /**
     * @param $st
     * @return bool
     */
    public static function modifyST($st)
    {
        return ScheduleTaskModel::modifyST($st);
    }

    /**
     * @param $st
     * @return mixed
     */
    public static function formatST($st) {
        if($st['status'] == ScheduleTaskModel::STATUS_NORMAL) {
            $st['st_status'] = $st['class_highest'] > count($st['student'])?ScheduleTaskModel::STATUS_UNFULL:ScheduleTaskModel::STATUS_FULL;
        }
        else {
            $st['st_status'] = $st['status'];
        }
        $st['st_status'] = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_TASK_STATUS,$st['st_status']);
        return $st;
    }
}
