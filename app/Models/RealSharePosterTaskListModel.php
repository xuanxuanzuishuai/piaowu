<?php
/**
 * author: qingfeng.lian
 * date: 2021/11/17
 */

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use Medoo\Medoo;

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
                'task_name' => '',
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

    /**
     * 获取活动分享任务活动数据
     * @param $activityIds
     * @return array
     */
    public static function getActivityTaskList($activityIds)
    {
        $db = MysqlDB::getDB();
        $list = $db->select(self::$table,
            [
                '[>]' . RealWeekActivityModel::$table => ['activity_id' => 'activity_id'],
                '[>]' . RealSharePosterPassAwardRuleModel::$table => ['activity_id' => 'activity_id'],
            ],
            [
                RealWeekActivityModel::$table . '.name',
                RealWeekActivityModel::$table . '.start_time',
                RealWeekActivityModel::$table . '.end_time',
                self::$table . '.task_num',
                self::$table . '.activity_id',
                "task_num_count" => Medoo::raw('max('.RealSharePosterPassAwardRuleModel::$table . '.success_pass_num)'),
                "activity_task" => Medoo::raw('concat_ws(:separator,'.self::$table . '.activity_id'.','.self::$table . '.task_num'.')',[":separator"=>'-']),
            ],
            [
                self::$table . '.activity_id' => $activityIds,
                'GROUP' => [self::$table . '.activity_id', self::$table . '.task_num',],
            ]);
        return empty($list) ? [] : $list;
    }
}
