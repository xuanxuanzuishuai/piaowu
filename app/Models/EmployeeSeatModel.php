<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2018/7/4
 * Time: 下午5:50
 */

namespace ERP\Models;


use ERP\Libs\MysqlDB;

class EmployeeSeatModel extends Model
{
    public static $table = "erp_employee_seat";
    public static $redisDB;

    const STATUS_NORMAL = 1;
    const STATUS_DEL = 0;

    //座席类型
    const SEAT_RONGLIAN = 1;                // 容联
    const SEAT_TIANRUN_MANUAL = 2;          // 天润手动外呼
    const SEAT_TIANRUN_AUTOMATIC = 3;       // 天润自动外呼
    const SEAT_TIANRUN_CUSTOMER_SERVICE = 4;// 天润客服

    /**
     * 判断seat_type是否天润
     * @param $seatType
     * @return bool
     */
    public static function isTRRT($seatType){
        if (in_array($seatType, [self::SEAT_TIANRUN_MANUAL, self::SEAT_TIANRUN_AUTOMATIC, self::SEAT_TIANRUN_CUSTOMER_SERVICE])) {
            return true;
        }
        return false;
    }

    /**
     * 获取座席信息
     * @param $where
     * @return mixed
     */
    public static function getEmployeeSeat($where)
    {
        return MysqlDB::getDB()->get(self::$table, '*', $where);
    }

    /**
     * 添加用户座席
     * @param $employeeId
     * @param $seatId
     * @param $seatType
     * @param $seatTel
     */
    public static function addEmployeeSeat($employeeId, $seatId, $seatType, $seatTel = null)
    {
        MysqlDB::getDB()->insert(self::$table, [
            'employee_id' => $employeeId,
            'seat_type' => $seatType,
            'seat_id' => $seatId,
            'seat_tel' => $seatTel ?: $seatId,
            'pwd' => '',
            'create_time' => time()
        ]);
    }

    /**
     * 删除用户座席
     * @param $employeeId
     */
    public static function delEmployeeSeat($employeeId)
    {
        MysqlDB::getDB()->delete(self::$table, ['employee_id'=>$employeeId]);
    }
}