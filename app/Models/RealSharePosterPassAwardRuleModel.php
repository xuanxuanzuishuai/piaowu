<?php
/**
 * author: qingfeng.lian
 * date: 2021/11/17
 */

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;

class RealSharePosterPassAwardRuleModel extends Model
{
    public static $table = 'real_share_poster_pass_award_rule';

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
        foreach ($taskList as $_taskNum => $_item) {
            $info = [
                'activity_id'      => $activityId,
                'success_pass_num' => $_taskNum + 1,
                'award_amount'     => $_item,
                'award_type'       => Constants::ERP_ACCOUNT_NAME_MAGIC,
                'create_time'      => $createTime,
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
     * 获取活动任务总数
     * @param $activityIds
     * @return array
     */
    public static function getActivityTaskCount($activityIds)
    {
        $sql = 'select activity_id,count(*) as total from ' . self::$table;
        if (!empty($activityIds)) {
            $sql .= ' where activity_id in(' . implode(',', $activityIds) . ')';
        }
        $sql .= ' group by activity_id';
        $list = MysqlDB::getDB()->queryAll($sql);
        return is_array($list) ? $list : [];
    }
}
