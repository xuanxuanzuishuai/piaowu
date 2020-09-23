<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 18/7/9
 * Time: 下午6:14
 */

namespace App\Models;

use App\Libs\MysqlDB;

class CallCenterLogModel extends Model
{
    public static $table = "callcenter_log";
    //呼叫类型
    const CALL_TYPE_IN = 1001; //call in
    const CALL_TYPE_OUT = 1002; //call out

    //座席类型
    const SEAT_TIANRUN = 1; //
    const SEAT_RONGLIAN = 2;
    /** 有效通话时长（秒） */
    const VALID_CONNECT_TIME = 5;
    /** 3天 */
    const VALID_CONNECT_EXPIRE = 3 * 86400;
    /** 3天8次 */
    const MAX_CONNECT_NUM = 8;

    /**
     * 保存通话日志
     * @param $params
     * @return mixed
     */
    public static function saveCallLog($params)
    {
        $db = MysqlDB::getDB();
        return $db->insertGetID(self::$table, $params);

    }

    /**
     * 更新通话日志 状态时间等
     * @param $params
     * @return mixed
     */
    public static function updateCallLog($params)
    {
        $data['ring_time'] = $params['ring_time'];
        $data['connect_time'] = $params['connect_time'];
        $data['finish_time'] = $params['finish_time'];
        $data['talk_time'] = $params['talk_time'];
        $data['call_status'] = $params['call_status'];
        $data['record_file'] = $params['record_file'];
        $data['show_code'] = $params['show_code'];
        $data['user_unique_id'] = $params['user_unique_id'];

        $res = MysqlDB::getDB()->updateGetCount(self::$table, $data, ['unique_id' => $params['unique_id']]);
        if ($res && $res > 0) {
            return true;
        }
        return false;
    }

    /**
     * 根据唯一标示获取通话数据
     * @param $unique_id
     * @return mixed
     */
    public static function getRecordByUniqueId($unique_id)
    {
        $record = MysqlDB::getDB()->get(self::$table, "*", ['unique_id' => $unique_id]);

        return $record;
    }

    /**
     * 获取用户通时通次
     * @param $where
     * @return array
     */
    public static function getUserRecord($where)
    {
        $join = [
            '[><]' . StudentModel::$table => [self::$table . '.student_id' => 'id'],
            '[><]' . EmployeeModel::$table => [self::$table . '.employee_id' => 'id'],
        ];

        $where['ORDER'] = [self::$table . ".create_time" => "DESC"];

        $data = MysqlDB::getDB()->select(self::$table, $join, [
            self::$table . '.id',
            self::$table . '.call_type',
            self::$table . '.seat_type',
            self::$table . '.seat_id',
            self::$table . '.create_time',
            self::$table . '.ring_time',
            self::$table . '.connect_time',
            self::$table . '.finish_time',
            self::$table . '.talk_time',
            self::$table . '.call_status',
            self::$table . '.record_file',
            self::$table . '.show_code',
            self::$table . '.cdr_enterprise_id',
            StudentModel::$table . '.name(student_name)',
            StudentModel::$table . '.mobile(student_mobile)',
        ], $where);

        return $data;
    }

    /**
     * 获取用户通时通次
     * @param $where
     * @param $fields
     * @return array
     */
    public static function getUserRecordCount($fields, $where)
    {
        $join = [
            '[><]' . StudentModel::$table => [self::$table . '.student_id' => 'id'],
            '[><]' . EmployeeModel::$table => [self::$table . '.employee_id' => 'id'],
        ];
        $data = MysqlDB::getDB()->get(self::$table, $join, $fields, $where);
        return $data;
    }

   /**
    * 根据用户唯一标示获取通话录音
    * @param $userUniqueId
    * @return mixed
    */
   public static function getRecordByUserUniqueId($userUniqueId)
   {
      $recordFile = MysqlDB::getDB()->get(self::$table, ["seat_type","cdr_enterprise_id","record_file",], ['user_unique_id' => $userUniqueId]);

      return $recordFile;
   }
}
