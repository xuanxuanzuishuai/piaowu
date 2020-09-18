<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2018/7/4
 * Time: 下午5:50
 */

namespace App\Models;


use App\Libs\MysqlDB;

class EmployeeSeatModel extends Model
{
    public static $cacheKeyPri = "employee_seat_";
    public static $table = "employee_seat";
    public static $redisDB;


    //座席类型
    const SEAT_RONGLIAN = 1;                // 容联
    const SEAT_TIANRUN_MANUAL = 2;          // 天润手动外呼
    const SEAT_TIANRUN_AUTOMATIC = 3;       // 天润自动外呼
    const SEAT_TIANRUN_CUSTOMER_SERVICE = 4;// 天润客服

    //是否在用 1 在用 0 通用
    const ON_USE = 1;
    const NOT_USE = 0;

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
     * @param $userId
     * @param $seatId
     * @param $seatType
     * @param $seatTel
     * @param $extendType
     */
    public static function addUserSeat($userId, $seatId, $seatType, $seatTel = null, $extendType = '')
    {
        MysqlDB::getDB()->insert(self::$table, [
            'employee_id' => $userId,
            'seat_type' => $seatType,
            'seat_id' => $seatId,
            'seat_tel' => $seatTel ?: $seatId,
            'pwd' => '',
            'extend_type' => $extendType,
            'create_time' => time()
        ]);
    }

    /**
     * 删除用户座席
     * @param $userId
     */
    public static function delUserSeat($userId)
    {
        MysqlDB::getDB()->delete(self::$table, ['employee_id' => $userId]);
    }

    /**
     * 删除座席
     * @param $id
     */
    public static function delById($id)
    {
        MysqlDB::getDB()->delete(self::$table, ['id' => $id]);
    }

    /**
     * 根据坐席获取用户ID
     * @param $seatId
     * @param $seatType
     * @return int|mixed
     */
    public static function getUserId($seatId, $seatType)
    {
        $userId = MysqlDB::getDB()->get(self::$table, 'employee_id', ['seat_id' => $seatId, 'seat_type' => $seatType]);
        return !empty($userId) ? $userId : 0;
    }

    /**
     * 获取座席信息
     * @param $where
     * @return mixed
     */
    public static function getUserSeat($where)
    {
        return MysqlDB::getDB()->get(self::$table, '*', $where);
    }
}