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
     * @param array $files
     * @return array
     * 查询多条记录
     */
    public static function getList($where,$files=[])
    {
        return self::getRecords($where,$files);
    }

    /**
     * @param $data
     * @param bool $isOrg
     * 批量插入数据
     * @return bool
     */
    public static function batchInsertData($data, bool $isOrg = false)
    {
        return self::batchInsert($data, $isOrg);
    }

    /**
     * @param $data
     * @param $where
     * @param bool $isOrg
     * @return int|null
     * 批量更新数据
     */
    public static function BatchUpdateData($data, $where,$isOrg = false)
    {
        return self::batchUpdateRecord($data, $where, $isOrg);
    }

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
