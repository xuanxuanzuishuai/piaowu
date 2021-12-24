<?php
/**
 * 白名单列表
 * User: yangpeng
 * Date: 2021/8/12
 * Time: 10:35 AM
 */

namespace App\Models;


class WeekWhiteListModel extends Model
{
    public static $table = "week_white_list";
    const NORMAL_STATUS = 1; //启用
    const DISABLE_STATUS = 2; //禁用

    /**
     * 根据学生id获取白名单列表
     * @param $studentId
     * @return array
     */
    public static function getListByStudentId($studentId)
    {
        return self::getRecords(['student_id'=>$studentId, 'status'=>WeekWhiteListModel::NORMAL_STATUS]);
    }
}