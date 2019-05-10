<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-28
 * Time: 12:22
 */

namespace App\Services;


use App\Libs\Constants;
use App\Models\ClassTaskModel;
use App\Models\ClassUserModel;
use App\Models\STClassModel;

class STClassService
{
    /**
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function getSTClassList($params, $page = -1, $count = 20)
    {
        list($num, $stcs) = STClassModel::getList($params, $page, $count);
        if (!empty($stcs)) {
            foreach ($stcs as $key => $stc) {
                $stcs[$key] = self::formatSTClass($stc);
            }
        }
        return [$num,$stcs];
    }


    /**
     * 获取班课详情
     * @param $id
     * @return array
     */
    public static function getSTClassDetail($id)
    {
        $stc = STClassModel::getDetail($id);
        if (!empty($stc)) {
            $stc = self::formatSTClass($stc);
            $cts = ClassTaskModel::getCTListByClassId($stc['id']);
            if (!empty($cts)) {
                foreach ($cts as $key => $ct) {
                    $cts[$key] = ClassTaskService::formatCT($ct);
                }
                $stc['class_tasks'] = $cts;
            }
            $cus = ClassUserModel::getCUListByClassId($stc['id']);
            if (!empty($cus)) {
                foreach ($cus as $key => $cu) {
                    $cu = ClassUserService::formatCU($cu);
                    if ($cu['user_role'] == ClassUserModel::USER_ROLE_S) {
                        $stc['students'][] = $cu;
                    } else {
                        $stc['teachers'][] = $cu;
                    }
                }
            }
        }
        return $stc;
    }

    /**
     * @param $stc
     * @return mixed
     */
    public static function formatSTClass($stc)
    {
        $stc['stc_status'] = DictService::getKeyValue(Constants::DICT_TYPE_CLASS_STATUS, $stc['status']);
        return $stc;
    }

    /**
     * @param $stc
     * @param $cts
     * @param array $students
     * @param array $teachers
     * @return bool
     */
    public static function addSTClass($stc, $cts, $students = [], $teachers = [])
    {

        $stcId = STClassModel::addSTClass($stc);
        if(!empty($stcId)) {
            $res = ClassTaskService::addCTs($stcId, $cts);
            if ($res == false) {
                return $res;
            }
            $cus = [];
            if (!empty($students)) {
                $cus[ClassUserModel::USER_ROLE_S] = $students;
            }
            if (!empty($teachers)) {
                $cus[ClassUserModel::USER_ROLE_T] = $teachers;
            }

            if (!empty($cus)) {
                $res = ClassUserService::bindCUs($stcId, $cus);
                if ($res == false) {
                    return $res;
                }
            }
        }
        return $stcId;
    }

    public static function modifyClass($stc) {
        return STClassModel::updateSTClass($stc['id'],$stc);
    }

    /**
     * @param $classId
     * @param $userId
     * @param $userRole
     * @return bool
     */
    public static function bindUser($classId, $userId, $userRole)
    {
        $now = time();
        $insert = [];

        $insert[] = ['class_id' => $classId, 'user_id' => $userId, 'user_role' => $userRole, 'status' => ClassUserModel::STATUS_NORMAL, 'create_time' => $now];

        $cus = ClassUserModel::getCUListByClassId($classId);
        foreach ($cus as $cu) {
            if ($cu['user_id'] == $userId && $cu['user_role'] == $userRole && $cu['status'] == ClassUserModel::STATUS_NORMAL)
                $delIds[] = $cu['id'];
        }
        if (!empty($delIds))
            ClassUserService::unBindUser($delIds,$classId);
        if (!empty($insert))
            ClassUserModel::batchInsert($insert);
        return true;
    }

    /**
     * 获取class信息
     * @param $classId
     * @return mixed|null
     */
    public static function getById($classId)
    {
        return STClassModel::getById($classId);
    }

    /**
     * 获取调课之后的classId
     * @param $scheduleId
     * @return mixed
     */
    public static function getClassByScheduleId($scheduleId)
    {
        return STClassModel::getRecord(['real_schedule_id' => $scheduleId, 'status' => STClassModel::STATUS_CHANGE], 'id');
    }
}