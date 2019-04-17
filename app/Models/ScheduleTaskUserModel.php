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

    public static function addSTU($insert) {
        return MysqlDB::getDB()->insert(self::$table,$insert);
    }

    public static function getSTUBySTIds($stIds,$userRole = array(1),$status = null ) {
        $where = ['id' => $stIds,'user_role'=>$userRole];
        if(isset($status)) {
            $where['user_status'] = $status;
        }
        $columns = [
            'user_id',
            'user_role',
            'id',
            'st_id',
            'create_time',
            'user_status',
        ];
        if($userRole == array(1)) {
            $join = [
                '[><]' . StudentModel::$table.' (s)' => ['user_id'=>'s.id']
            ];
            $columns['s.name(student_name)'];
        }
        else {
            $join = [
                '[><]' . TeacherModel::$table.'(t)' => ['user_id' => 't.id']
            ];
            $columns['t.name(teacher_name)'];
        }

        return MysqlDB::getDB()->select(self::$table,$join,'*',$where);
    }

    public static function getSTUDetail($id) {
        return MysqlDB::getDB()->get(self::$table,'*',['id'=>$id]);
    }

    public static function updateSTUStatus($ids,$status) {
        return MysqlDB::getDB()->updateGetCount(self::$table,['status'=>$status],['id'=>$ids]);
    }
}