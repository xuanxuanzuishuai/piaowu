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
}