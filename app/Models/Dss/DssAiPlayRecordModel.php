<?php

namespace App\Models\Dss;

use Medoo\Medoo;

class DssAiPlayRecordModel extends DssModel
{
    public static $table = "ai_play_record";

    /**
     * 获取累计练琴天数
     * @param $studentId
     * @return int
     */
    public static function getAccumulateDays($studentId)
    {
        $db = self::dbRO();
        $countCol = Medoo::raw("COUNT(DISTINCT FROM_UNIXTIME(end_time, '%y-%m-%d'))");
        $countResult = $db->get(self::$table, ['count' => $countCol], ['student_id' => $studentId]);
        return !empty($countResult['count']) ? (INT)$countResult['count'] : 0;
    }
}