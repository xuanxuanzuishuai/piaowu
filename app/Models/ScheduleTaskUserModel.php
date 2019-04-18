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
        $where = ['id' => $stIds];
        $columns = [
            'user_id',
            'user_role',
            'id',
            'st_id',
            'create_time',
            'user_status',
            't.name(teacher_name)',
            's.name(student_name)'

        ];

        $join = [
            '[>]' . StudentModel::$table . ' (s)' => ['user_id' => 's.id','status' => $status],
            '[>]' . TeacherModel::$table . '(t)' => ['user_id' => 't.id','status' => $status]
        ];

        return MysqlDB::getDB()->select(self::$table, $join, $columns, $where);
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
     * @param $ids
     * @param $status
     * @param null $now
     * @return int|null
     */
    public static function updateSTUStatus($ids, $status, $now = null)
    {
        if (empty($now)) {
            $now = time();
        }
        return MysqlDB::getDB()->updateGetCount(self::$table, ['status' => $status, 'update_time'=>$now], ['id' => $ids]);
    }
}