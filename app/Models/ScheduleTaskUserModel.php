<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 19:04
 */

namespace App\Models;


use App\Libs\MysqlDB;

class ScheduleTaskUserModel extends Model
{
    public static $table = "schedule_task_user";
    public static $redisExpire = 0;
    public static $redisDB;

    const USER_ROLE_S = 1;//学员角色
    const USER_ROLE_T = 2;//老师角色

    const STATUS_CANCEL = 0;
    const STATUS_NORMAL = 1;
    const STATUS_BACKUP = 2;

    /**
     * @param $insert
     * @return \PDOStatement
     */
    public static function addSTU($insert)
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

        $sql = "select stu.user_id,stu.price,stu.user_role,stu.id,stu.st_id,stu.create_time,stu.status,t.name as teacher_name,s.name as student_name from ".self::$table ." as stu "
            ." left join ".StudentModel::$table." as s on stu.user_id = s.id and stu.user_role = ".self::USER_ROLE_S
            ." left join ".TeacherModel::$table." as t on stu.user_id = t.id and stu.user_role = ".self::USER_ROLE_T
            ." where stu.st_id in (".implode(',',$stIds).") and stu.status in (".implode(",",$status).")";

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
    public static function updateSTUStatus($where, $status, $now = null)
    {
        if (empty($now)) {
            $now = time();
        }
        return MysqlDB::getDB()->updateGetCount(self::$table, ['status' => $status, 'update_time'=>$now], $where);
    }
}