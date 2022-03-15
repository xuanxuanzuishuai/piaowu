<?php
/**
 * author: qingfeng.lian
 * date: 2021/11/17
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;

class SharePosterTaskListModel extends Model
{
    public static $table = 'share_poster_task_list';

    /**
     * 批量写入数据
     * @param $activityId
     * @param $taskList
     * @param $createTime
     * @param string $activityName
     * @return bool
     */
    public static function batchInsertActivityTask($activityId, $taskList, $createTime, string $activityName = ''): bool
    {
        $realSharePosterTaskRuleData = [];
        foreach ($taskList as $_taskNum => $_item) {
            $num = $_taskNum + 1;
            $info = [
                'activity_id' => $activityId,
                'task_name' => !empty($_item['task_name']) ? trim($_item['task_name']) : $num . trim($activityName),
                'task_num' => $num,
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
     * @param string $activityName
     * @return bool
     */
    public static function batchUpdateActivityTask($activityId, $taskList, $createTime, string $activityName = ''): bool
    {
        SimpleLogger::info("batchUpdateRuleTaskAwardData", [$activityId, $taskList, $createTime]);
        $delRes = MysqlDB::getDB()->delete(self::$table, [
            'activity_id' => $activityId,
        ]);
        if ($delRes->errorCode() != \PDO::ERR_NONE) {
            return false;
        }
        return self::batchInsertActivityTask($activityId, $taskList, $createTime, $activityName);
    }
}
