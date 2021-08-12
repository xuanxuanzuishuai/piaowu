<?php
/**
 * Created by PhpStorm.
 * User: sunchanghui
 * Date: 2021-08-10 15:37:00
 * Time: 11:58 AM
 */

namespace App\Models\CHModel;


use App\Libs\CHDB;

class AprViewRecordModel extends CHOBModel
{
    /**
     * ai_play_record表 ORDER BY (record_id, id)
     */
    public static $table = 'apr_view_record_all';
    
    /**
     * @param $recordId
     * @return array|mixed
     * 当前演奏详情数据
     */
    public static function getRecordIdInfo($recordId)
    {
        $chdb = CHDB::getBODB();
        $sql  = "
            SELECT
                input_type,
                audio_url,
                student_id,
                score_rank,
                score_final,
                score_complete,
                score_pitch,
                score_rhythm,
                score_speed,
                score_speed_average,
                score_rank,
                lesson_id,
                record_id,
                is_phrase,
                hand
            FROM
                {table}
            WHERE
                record_id = {id}
                order by ts desc
            limit 1 by id
        ";
        $result = $chdb->queryAll($sql, ['table' => self::$table, 'id' => $recordId]);
        return empty($result) ? [] : $result[0];
    }
}
