<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/5
 * Time: 8:17 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;

class StudentFavoriteModel extends Model
{
    //收藏类型
    const FAVORITE_TYPE_LESSON = 1;
    const FAVORITE_TYPE_COLLECTION = 2;

    //收藏状态
    const FAVORITE_SUCCESS = 1;
    const FAVORITE_CANCEL = 2;
    const FAVORITE_NOT = 3;

    public static $table = 'student_favorite';

    /**
     * @param $studentId
     * @return array|array[]
     * 获取指定学生最近10条曲谱和教材收藏列表
     */
    public static function getFavoriteIds($studentId)
    {
        $studentFavorite = self::$table;
        $sql = "SELECT
                    type,
                    object_id 
                FROM
                    (
                    SELECT
                        type,
                        object_id,
                        create_time,
                        ROW_NUMBER ( ) over ( PARTITION BY student_id, type ORDER BY create_time DESC ) AS s 
                    FROM
                        {$studentFavorite} s 
                    WHERE
                        student_id = {$studentId} AND status = 1
                    ) tmp 
                WHERE
                    s <= 10 
                ORDER BY
                    create_time DESC";

        $db = MysqlDB::getDB();
        $result = $db->queryAll($sql);
        if (empty($result)) {
            return [];
        }

        foreach ($result as $value) {
            if ($value['type'] == self::FAVORITE_TYPE_LESSON) {
                $lessonIds[] = $value['object_id'];
            } elseif ($value['type'] == self::FAVORITE_TYPE_COLLECTION) {
                $collectionIds[] = $value['object_id'];
            }
        }
        return [
            "lessonIds"      => $lessonIds ?? [],
            "collectionIds" => $collectionIds ?? [],
        ];
    }
}