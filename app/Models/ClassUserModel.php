<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 19:04
 */

namespace App\Models;


use App\Libs\MysqlDB;

class ClassUserModel extends Model
{
    public static $table = "class_user";
    public static $redisExpire = 0;
    public static $redisDB;

    const USER_ROLE_S = 1;//学员角色
    const USER_ROLE_T = 2;//老师角色
    const USER_ROLE_HT = 3;//班主任

    const STATUS_CANCEL = 0;
    const STATUS_NORMAL = 1;
    const STATUS_BACKUP = 2;

    /**
     * @param $insert
     * @return \PDOStatement
     */
    public static function addCU($insert)
    {
        return MysqlDB::getDB()->insertGetID(self::$table, $insert);
    }

    /**
     * @param $stIds
     * @param array $status
     * @return array
     */
    public static function getSTUBySTIds($stIds, $status = array(self::STATUS_NORMAL,self::STATUS_BACKUP))
    {

        $sql = "select cu.user_id,cu.price,cu.user_role,cu.id,cu.st_id,cu.create_time,cu.status,t.name as teacher_name,s.name as student_name from ".self::$table ." as cu "
            ." left join ".StudentModel::$table." as s on cu.user_id = s.id and cu.user_role = ".self::USER_ROLE_S
            ." left join ".TeacherModel::$table." as t on cu.user_id = t.id and cu.user_role in (".self::USER_ROLE_T.",".self::USER_ROLE_HT.")"
            ." where cu.class_id in (".implode(',',$stIds).") and cu.status in (".implode(",",$status).")";

        return MysqlDB::getDB()->queryAll($sql,\PDO::FETCH_COLUMN);
    }

    /**
     * @param $id
     * @return mixed
     */
    public static function getSTUDetail($id)
    {
        return MysqlDB::getDB()->get(self::$table, '*', ['id' => $id]);
    }

    /**
     * @param $where
     * @param $status
     * @param null $now
     * @return int|null
     */
    public static function updateCUStatus($where, $status, $now = null)
    {
        if (empty($now)) {
            $now = time();
        }
        return MysqlDB::getDB()->updateGetCount(self::$table, ['status' => $status, 'update_time'=>$now], $where);
    }

    /**
     * @param $classId
     * @param null $status
     * @return array|null
     */
    public static function getCUListByClassId($classId,$status = null) {
        $status = is_null($status) ? self::STATUS_NORMAL : $status;
        $sql = "select cu.user_id,cu.price,cu.user_role,cu.id,cu.class_id,cu.create_time,cu.status,t.name as teacher_name,s.name as student_name from ".self::$table ." as cu "
            ." inner join ".StudentModel::$table." as s on cu.user_id = s.id and cu.user_role = ".self::USER_ROLE_S
            ." inner join ".TeacherModel::$table." as t on cu.user_id = t.id and cu.user_role in( ".self::USER_ROLE_T.",".self::USER_ROLE_HT.")"
            ." where cu.class_id = $classId and cu.status in (".implode(",",$status).")";

        return MysqlDB::getDB()->queryAll($sql,\PDO::FETCH_COLUMN);
    }


}