<?php
/**
 * author: qingfeng.lian
 * date: 2021/11/11
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use PDO;

class RealSharePosterDesignateUuidModel extends Model
{
    public static $table = 'real_share_poster_designate_uuid';

    /**
     * 批量写入数据
     * @param $activityId
     * @param $uuidList
     * @param $operatorId
     * @param $createTime
     * @return bool
     */
    public static function batchInsertUuid($activityId, $uuidList, $operatorId, $createTime): bool
    {
        $realSharePosterTaskRuleData = [];
        foreach ($uuidList as $_uuid) {
            $info                          = [
                'activity_id' => $activityId,
                'uuid'        => $_uuid,
                'operator_id' => $operatorId,
                'create_time' => $createTime,
            ];
            $realSharePosterTaskRuleData[] = $info;
        }
        unset($_uuid);
        return self::batchInsert($realSharePosterTaskRuleData);
    }

    /**
     * 删除数据
     * @param $activityId
     * @param $uuid
     * @param $employId
     * @return bool
     */
    public static function delDesignateUUID($activityId, $uuid, $employId): bool
    {
        SimpleLogger::info("delDesignateUUID", [$activityId, $uuid, $employId]);
        $delWhere = [
            'activity_id' => $activityId,
        ];
        if (!empty($uuid)) {
            $delWhere['uuid'] = $uuid;
        }
        $delRes = MysqlDB::getDB()->delete(self::$table, $delWhere);
        if ($delRes->errorCode() != PDO::ERR_NONE) {
            return false;
        }
        return true;
    }

    /**
     * 根据搜索条件获取指定的UUID列表
     * @param $params
     * @param $limit
     * @param array $order
     * @return array
     */
    public static function searchList($params, $page, $limit, array $order = []): array
    {
        $limitOffset = ($page - 1) * $limit;
        $where = [];
        if (isset($params['activity_id']) && !Util::emptyExceptZero($params['activity_id'])) {
            $where['w.activity_id'] = $params['activity_id'];
        }
        $total = MysqlDB::getDB()->count(self::$table . '(w)', $where);
        if ($total <= 0) {
            return [[], 0];
        }
        // 计算是否超过总数 - 超过总数说明数据已经到最后一页
        if ($total < $limitOffset) {
            return [[], $total];
        }
        if (!empty($limit)) {
            $where['LIMIT'] = [$limitOffset, $limit];
        }
        if (empty($order)) {
            $order = ['id' => 'DESC'];
        }
        $where['ORDER'] = $order;

        $list = MysqlDB::getDB()->select(
            self::$table . '(w)',
            [
                '[>]' . EmployeeModel::$table . '(e)' => ['w.operator_id' => 'id'],
            ],
            [
                'w.id',
                'w.activity_id',
                'w.uuid',
                'w.operator_id',
                'w.create_time',
                'e.name(operator_name)',
            ],
            $where
        );
        return [$list, $total];
    }

    /**
     * 获取uuid指定可参与的周周领奖活动
     * @param $studentUUID
     * @param $time
     * @return array|mixed
     */
    public static function getUUIDDesignateWeekActivityList($studentUUID, $time)
    {
        $records = MysqlDB::getDB()->select(
            self::$table . '(d)',
            [
                '[>]' . RealWeekActivityModel::$table . '(w)' => ['d.activity_id' => 'activity_id'],
            ],
            [
                'w.activity_id',
                'w.target_user_type',
                'w.target_use_first_pay_time_start',
                'w.target_use_first_pay_time_end',
                'w.priority_level',
                'w.start_time',
                'w.end_time',
            ],
            [
                'w.start_time[<]' => $time,
                'w.end_time[>]' => $time,
                'w.enable_status' => OperationActivityModel::ENABLE_STATUS_ON,
                'd.uuid' => $studentUUID
            ]
        );
        return $records[0] ?? [];
    }

    /**
     * 获取活动下的指定UUID列表
     * @param $activityId
     * @return array
     */
    public static function getUUIDByActivityId($activityId): array
    {
        return MysqlDB::getDB()->select(
            self::$table . '(d)',
            [
                '[>]' . EmployeeModel::$table . '(e)' => ['d.operator_id' => 'id'],
            ],
            [
                'd.activity_id',
                'd.uuid',
                'd.create_time',
                'd.operator_id',
                'e.name (operator_name)',
            ],
            [
                'd.activity_id' => $activityId,
                'ORDER' => ['d.id' => 'ASC']
            ]
        );
    }
}
