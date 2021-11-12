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
     * 修改数据 - 先删除，再添加
     * @param $activityId
     * @param $uuidList
     * @param $operatorId
     * @param $createTime
     * @return bool
     */
    public static function batchUpdateUuid($activityId, $uuidList, $operatorId, $createTime): bool
    {
        SimpleLogger::info("batchUpdateUuid", [$activityId, $uuidList, $operatorId, $createTime]);
        $delRes = self::delDesignateUUID($activityId, $uuidList, $operatorId);
        if (empty($delRes)) {
            return false;
        }
        return self::batchInsertUuid($activityId, $uuidList, $operatorId, $createTime);
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
        $delRes = MysqlDB::getDB()->delete(self::$table, [
            'activity_id' => $activityId,
            'uuid'        => $uuid,
        ]);
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
}
