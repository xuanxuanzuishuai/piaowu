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

class SharePosterPassAwardRuleModel extends Model
{
    public static $table = 'share_poster_pass_award_rule';

    /**
     * 批量写入数据
     * @param $activityId
     * @param $taskList
     * @param $createTime
     * @return bool
     */
    public static function batchInsertPassAwardRule($activityId, $taskList, $createTime): bool
    {
        $realSharePosterTaskRuleData = [];
        foreach ($taskList as $_taskNum => $taskAward) {
            $info = [
                'activity_id' => $activityId,
                'success_pass_num' => $_taskNum + 1,
                'award_amount' => $taskAward,
                'award_type' => Constants::ERP_ACCOUNT_NAME_GOLD_LEFT,
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
    public static function batchUpdatePassAwardRule($activityId, $taskList, $createTime): bool
    {
        SimpleLogger::info("batchUpdateRuleTaskAwardData", [$activityId, $taskList, $createTime]);
        $delRes = MysqlDB::getDB()->delete(self::$table, [
            'activity_id' => $activityId,
        ]);
        if ($delRes->errorCode() != \PDO::ERR_NONE) {
            return false;
        }
        return self::batchInsertPassAwardRule($activityId, $taskList, $createTime);
    }

    /**
     * 获取活动分享任务列表
     * @param $activityIds
     * @return array
     */
    public static function getActivityTaskList($activityIds)
    {
        $db = MysqlDB::getDB();
        $list = $db->select(self::$table,
            [
                '[>]' . WeekActivityModel::$table => ['activity_id' => 'activity_id'],
            ],
            [
                WeekActivityModel::$table . '.name',
                WeekActivityModel::$table . '.start_time',
                WeekActivityModel::$table . '.end_time',
                self::$table . '.task_num',
                self::$table . '.activity_id',
                "activity_task" => Medoo::raw('concat_ws(:separator,'.self::$table . '.activity_id'.','.self::$table . '.task_num'.')',[":separator"=>'-']),
            ],
            [
                self::$table . '.activity_id' => $activityIds,
                'GROUP' => [self::$table . '.activity_id', self::$table . '.task_num',],
            ]);
        return empty($list) ? [] : $list;
    }

    /**
     * 获取活动奖励规则 - 取最大的通过次数的奖励
     * @param $activityIds
     * @return array|false
     */
    public static function getPassAwardRuleList($activityIds)
    {
        if (empty($activityIds)) {
            return [];
        }
        $sql = "select tmp.*, w.start_time, w.end_time, w.name activity_name from (
            SELECT
                id,
                activity_id,
                success_pass_num,
                dense_rank() over ( PARTITION BY activity_id ORDER BY success_pass_num DESC ) AS upload_order,
                concat_ws('_',activity_id, success_pass_num) activity_max_num
            FROM
                ". self::$table ."
        ) tmp 
        left join ". WeekActivityModel::$table . " w on w.activity_id=tmp.activity_id
        WHERE tmp.upload_order=1 and tmp.activity_id IN (" . implode(',', $activityIds) . ')';
        $list = MysqlDB::getDB()->queryall($sql);
        return is_array($list) ? $list : [];
    }
}
