<?php
/**
 * Landing页召回
 */

namespace App\Models;

use App\Libs\MysqlDB;

class LandingRecallLogModel extends Model
{
    public static $table = 'landing_recall_log';
    
    /**
     * 发送统计
     * @param $date
     * @return array|null
     */
    public static function getSendCount($date)
    {
        $where = '';
        if ($date) {
            $where = "AND create_date = '{$date}'";
        }
        $table = self::$table;
        $sql = "
            SELECT
                COUNT(IF(sms=1,mobile,NULL)) AS sms_pv,
                COUNT(DISTINCT(IF(sms=1,mobile,NULL))) AS sms_uv,
                COUNT(IF(voice=1,mobile,NULL)) AS voice_pv,
                COUNT(DISTINCT(IF(voice=1,mobile,NULL))) AS voice_uv
            FROM
                {$table}
            WHERE
                1=1 {$where};
        ";
        $db = MysqlDB::getDB();
        return $db->queryAll($sql);
    }
}
