<?php

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use Medoo\Medoo;

class StudentCollectionExceptModel extends Model
{
    public static $table = "student_collection_expect";

    /**
     * @param $studentId
     * @return array|null
     * 获取学生是否对指定集合点赞
     */
    public static function isExceptByStudent($studentId)
    {
        return MysqlDB::getDB()->select(self::$table, ['collection_id'], [
            'student_id' => $studentId,
        ]);
    }

    /**
     * @param $collectionIds
     * @return array|null
     * 获取指定集合当前的期待的总数
     */
    public static function collectionExpectNum($collectionIds)
    {
        $collectionIds = "(" . implode(',', $collectionIds) . ")";
        $collectionExcept = self::$table;
        $sql = "select collection_id,count(1) as num from {$collectionExcept} where collection_id in " . $collectionIds . " group by collection_id";
        $db = MysqlDB::getDB();
        return $db->queryAll($sql);
    }
}
