<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/05/22
 * Time: 14:17 AM
 */

namespace App\Models;

class StudentAcquiredLogModel extends Model
{
    public static $table = 'student_acquired_log';
    //操作类型:1获取手机号
    const OPERATE_TYPE_GET_MOBILE = 1;

    /**
     * 格式化数据
     * @param $studentIds
     * @param $employeeId
     * @param $time
     * @param $operateType
     * @return array
     */
    public static function formatLogData($studentIds, $employeeId, $time, $operateType)
    {
        $data = [];
        foreach ($studentIds as $id) {
            $row = [];
            $row['student_id'] = $id;
            $row['create_time'] = $time;
            $row['operator_id'] = $employeeId;
            $row['operate_type'] = $operateType;
            $data[] = $row;
        }
        return $data;
    }
}