<?php

namespace App\Models\CHModel;


use App\Libs\CHDB;
use App\Models\Erp\ErpStudentAccountDetail;

class StudentAccountDetailModel
{

    public static $table = "erp_student_account_detail_all";


    /**
     * 某段时间内指定学生的账户增加
     * @param $studentId
     * @param $startTime
     * @param $endTime
     * @param int $subType
     * @return array
     */
    public static function timeRangeOnlyAdd($studentId, $startTime, $endTime, $subType = ErpStudentAccountDetail::SUB_TYPE_GOLD_LEAF)
    {
        $chDb = CHDB::getErpDB();
        if (is_array($studentId)) {
            $studentId = implode(',', $studentId);
        }
        $sql = 'select student_id,sum(num) total from ' . self::$table . ' esad where esad.sub_type = ' . $subType . ' and esad.operate_type = 1 and (esad.create_time >= '. $startTime .' and esad.create_time < ' . $endTime . ') and student_id in (' . $studentId . ') group by student_id';
        return $chDb->queryAll($sql);
    }


    /**
     * 哪些用户余额有变动
     * @param $startTime
     * @param $endTime
     * @param bool $needExpire
     * @param int $subType
     * @return array
     */
    public static function getHasChangeStudentId($startTime, $endTime, $needExpire = true, $subType = ErpStudentAccountDetail::SUB_TYPE_GOLD_LEAF)
    {
        $chDb = CHDB::getErpDB();
        $or = $needExpire ? ' or (esad.expire_time  >= ' . $startTime . ' and esad .expire_time < ' . $endTime . ')' : NULL;
        $sql = 'select distinct student_id from ' . self::$table . ' esad where esad.sub_type = ' . $subType . ' and ((esad.create_time >= ' . $startTime . ' and esad.create_time < ' . $endTime . ')'. $or .')';

        return $chDb->queryAll($sql);
    }
}
