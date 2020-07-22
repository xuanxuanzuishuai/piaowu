<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/5
 * Time: 8:33 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;

class StudentWorkOrderReplayModel extends Model
{
    const STATUS_NORMAL = 1;
    const STATUS_DEL = 0;

    public static $table = "student_work_order_reply";

    /**
     * @param $where
     * @param $data
     * @return int|null
     * 更新记录
     */
    public static function updateData($where, $data)
    {
        if (empty($where)) {
            return 0;
        }
        if (empty($data)) {
            return 0;
        }
        $db = MysqlDB::getDB();
        return $db->updateGetCount(static::$table, $data, $where);
    }

}
