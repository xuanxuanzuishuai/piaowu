<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/08/19
 * Time: 10:54 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;

class HistoryRanksModel extends Model
{
    static $table = 'history_ranks';
    //排行榜类型:1季度排名
    const RANK_TYPE_QUARTER = 1;

    /**
     * @param $issueNumber
     * @param $lessonId
     * @param int $type
     * @return array
     */
    public static function getRankList($issueNumber, $lessonId, $type = self::RANK_TYPE_QUARTER)
    {
        $db = MysqlDB::getDB();
        $ranks = $db->select(self::$table,
            [
                '[><]' . StudentModel::$table => ['student_id' => 'id']
            ],
            [
                self::$table . '.lesson_id',
                self::$table . '.student_id',
                self::$table . '.ai_record_id',
                self::$table . '.score',
                self::$table . '.play_id',
                StudentModel::$table . '.name',
                StudentModel::$table . '.thumb',
            ],
            [
                self::$table . '.type' => $type,
                self::$table . '.issue_number' => $issueNumber,
                self::$table . '.lesson_id' => $lessonId,
                "ORDER" => [self::$table . '.score' => "DESC"],
                "LIMIT" => AIPlayRecordModel::RANK_LIMIT
            ]);
        return $ranks;
    }
}