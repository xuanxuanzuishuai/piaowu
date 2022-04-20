<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/21
 * Time: 10:52
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssGiftCodeModel;

class RealWeekActivityPosterAbModel extends Model
{
    public static $table = "real_week_activity_poster_ab";
    const IS_CONTRAST_YES = 1;  // 是否是对照组， 1是
    const IS_CONTRAST_NO  = 0;  // 是否是对照组， 0否

    /**
     * 删除数据
     * @param $activityId
     * @return bool
     */
    public static function batchDelByActivity($activityId): bool
    {
        SimpleLogger::info("batchDelByActivity", [$activityId]);
        $delRes = MysqlDB::getDB()->delete(self::$table, [
            'activity_id' => $activityId,
        ]);
        if ($delRes->errorCode() != \PDO::ERR_NONE) {
            return false;
        }
        return true;
    }

    /**
     * 获取使用海报的周周领奖 - 活动未结束未禁用的
     * @param $posterId
     * @return array
     */
    public static function getAbPosterActivityIds($posterId)
    {
        $time = time();
        $table1 = self::$table;
        $table2 = RealWeekActivityModel::$table;
        if (empty($table2)) {
            return [];
        }
        $status21 = OperationActivityModel::ENABLE_STATUS_OFF;
        $status22 = OperationActivityModel::ENABLE_STATUS_ON;
        $sql = "
            SELECT
                {$table1}.id,{$table1}.activity_id
            FROM
                {$table1}
                INNER JOIN {$table2} ON {$table2}.activity_id = {$table1}.activity_id
            WHERE
                {$table1}.poster_id = {$posterId}
                AND {$table2}.enable_status IN ({$status21},{$status22})
                AND {$table2}.end_time > {$time}
        ";
        $db = MysqlDB::getDB();
        $res = $db->queryAll($sql);
        return is_array($res) ? $res : [];
    }
}