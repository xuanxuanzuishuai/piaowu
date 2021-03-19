<?php


namespace App\Models;


use App\Libs\MysqlDB;

class StudentAccountAwardPointsFileModel extends Model
{
    public static $table = 'student_account_award_points_file';

    const STATUS_CREATE = 0; //创建未执行
    const STATUS_EXEC = 1; //执行中
    const STATUS_COMPLETE = 2; //执行完成


    /**
     * 更新为状态 STATUS_CREATE > STATUS_EXEC
     * @param $id
     * @param $status
     * @return int|null
     */
    public static function updateStatusExecById($id)
    {
        $where = ['id' => $id,'status' => self::STATUS_CREATE];
        $data = [
            'status' => self::STATUS_EXEC,
            'update_time' => time(),
        ];
        $db = MysqlDB::getDB();
        $cnt = $db->updateGetCount(static::$table, $data, $where);
        static::delCache($id);
        return $cnt;
    }

    /**
     * 更新为状态 STATUS_CREATE > STATUS_COMPLETE
     * @param $id
     * @param $status
     * @return int|null
     */
    public static function updateStatusCreateToCompleteById($id)
    {
        $where = ['id' => $id,'status' => self::STATUS_CREATE];
        $data = [
            'status' => self::STATUS_COMPLETE,
            'update_time' => time(),
        ];
        $db = MysqlDB::getDB();
        $cnt = $db->updateGetCount(static::$table, $data, $where);
        static::delCache($id);
        return $cnt;
    }

    /**
     * 更新为状态 STATUS_EXEC > STATUS_COMPLETE
     * @param $id
     * @param $status
     * @return int|null
     */
    public static function updateStatusExecToCompleteById($id)
    {
        $where = ['id' => $id,'status' => self::STATUS_EXEC];
        $data = [
            'status' => self::STATUS_COMPLETE,
            'update_time' => time(),
        ];
        $db = MysqlDB::getDB();
        $cnt = $db->updateGetCount(static::$table, $data, $where);
        static::delCache($id);
        return $cnt;
    }

}