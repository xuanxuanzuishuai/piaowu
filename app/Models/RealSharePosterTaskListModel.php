<?php
/**
 * author: qingfeng.lian
 * date: 2021/11/17
 */

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;

class RealSharePosterTaskListModel extends Model
{
    public static $table = 'real_share_poster_task_list';

    /**
     * 批量写入数据
     * @param $activityId
     * @param $taskList
     * @param $createTime
     * @return bool
     */
    public static function batchInsertActivityTask($activityId, $taskList, $createTime): bool
    {
        $realSharePosterTaskRuleData = [];
        foreach ($taskList as $_taskNum => $_item) {
            $info = [
                'activity_id' => $activityId,
                'task_name' => $_item['task_name'],
                'task_num' => $_taskNum + 1,
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
    public static function batchUpdateActivityTask($activityId, $taskList, $createTime): bool
    {
        SimpleLogger::info("batchUpdateRuleTaskAwardData", [$activityId, $taskList, $createTime]);
        $delRes = MysqlDB::getDB()->delete(self::$table, [
            'activity_id' => $activityId,
        ]);
        if ($delRes->errorCode() != \PDO::ERR_NONE) {
            return false;
        }
        return self::batchInsertActivityTask($activityId, $taskList, $createTime);
    }
}
