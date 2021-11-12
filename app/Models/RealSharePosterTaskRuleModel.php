<?php
/**
 * author: qingfeng.lian
 * date: 2021/11/11
 */

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use PDO;

class RealSharePosterTaskRuleModel extends Model
{
    public static $table = 'real_share_poster_task_rule';

    /**
     * 批量写入数据
     * @param $activityId
     * @param $taskList
     * @param $createTime
     * @return bool
     */
    public static function batchInsertRuleTaskAwardData($activityId, $taskList, $createTime): bool
    {
        $realSharePosterTaskRuleData = [];
        foreach ($taskList as $_taskNum => $_item) {
            $info = [
                'activity_id' => $activityId,
                'task_name' => $_item['task_name'],
                'task_num' => $_taskNum + 1,
                'task_award' => $_item['task_award'],
                'award_type' => Constants::ERP_ACCOUNT_NAME_MAGIC,
                'create_time' => $createTime,
            ];
            $realSharePosterTaskRuleData[] = $info;
        }
        unset($_taskNum, $_item);
        return self::batchInsert($realSharePosterTaskRuleData);
    }

    /**
     * 修改数据 - 先删除，再添加
     * @param $activityId
     * @param $taskList
     * @param $createTime
     * @return bool
     */
    public static function batchUpdateRuleTaskAwardData($activityId, $taskList, $createTime): bool
    {
        SimpleLogger::info("batchUpdateRuleTaskAwardData", [$activityId, $taskList, $createTime]);
        $delRes = MysqlDB::getDB()->delete(self::$table, [
            'activity_id' => $activityId,
        ]);
        if ($delRes->errorCode() != PDO::ERR_NONE) {
            return false;
        }
        return self::batchInsertRuleTaskAwardData($activityId, $taskList, $createTime);
    }
}
