<?php


namespace App\Models;


use App\Libs\MysqlDB;
use Medoo\Medoo;

class LeadsPoolLogModel extends Model
{
    /**
     * 配置变更
     */
    const TYPE_POOL_RULE_CACHE_UPDATE = 1001;
    const TYPE_COUNTER_CACHE_UPDATE = 1002;

    /**
     * 分配事件
     */
    const TYPE_ADDED = 2001; // pool 添加
    const TYPE_DISPATCHED = 2002; // pool 分配
    const TYPE_STASHED = 2003; // pool 堆积
    const TYPE_ASSIGN = 2004; // 分配到助教
    const TYPE_MOVE = 2005; // 分配到池
    const TYPE_PREPARE = 2006; // 预处理

    const TYPE_REF_ASSIGN = 2101; // 转介绍直接分配

    /**
     * 错误事件
     */
    const TYPE_ERROR_NO_RULES = 9001; // 未配置规则
    const TYPE_NO_COLLECTION = 9002; // 无可分配班级
    const TYPE_SET_COLLECTION_ERROR = 9003; // 分班失败

    public static $table = 'leads_pool_log';

    public static function getDailyCount($poolId, $date)
    {
        $db = MysqlDB::getDB();

        $where = [
            'pool_id' => $poolId,
            'date' => $date,
            'GROUP' => 'type'
        ];

        $result = $db->select(self::$table . '(lpl)',
            [
                'type',
                'count' => Medoo::raw('COUNT(*)'),
            ],
            $where
        );

        $result = array_column($result, 'count', 'type');
        if (empty($result[self::TYPE_ADDED])) { $result[self::TYPE_ADDED] = 0; }
        if (empty($result[self::TYPE_DISPATCHED])) { $result[self::TYPE_DISPATCHED] = 0; }
        if (empty($result[self::TYPE_STASHED])) { $result[self::TYPE_STASHED] = 0; }

        return $result;
    }
}