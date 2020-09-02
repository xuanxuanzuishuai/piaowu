<?php


namespace App\Services\LeadsPool;


use App\Libs\RedisDB;
use App\Models\EmployeeModel;
use App\Models\LeadsPoolLogModel;
use App\Models\LeadsPoolModel;
use App\Models\LeadsPoolRuleModel;

class CacheManager
{
    const CONFIG_EXPIRE = 86400 * 3; // 缓存保存3天
    const CACHE_POOL_COUNTER = 'pool_counter';
    const CACHE_POOL_CAPACITY = 'pool_capacity';
    const CACHE_POOL_RULES = 'pool_rules';

    public static function getCacheKey($type, $id, $date)
    {
        if (empty($type) || empty($id)) {
            return '';
        }
        return "{$type}_{$id}_{$date}";
    }

    public static function getEmployeePoolConfig($poolId, $date)
    {
        $pool = EmployeeModel::getById($poolId);
        $counterCache = self::getPoolDailyCounterCache($poolId, $date);

        $pool['added'] = $counterCache[LeadsPoolLogModel::TYPE_ADDED] ?? 0;
        $pool['stashed'] = $counterCache[LeadsPoolLogModel::TYPE_STASHED] ?? 0;
        $pool['dispatched'] = $counterCache[LeadsPoolLogModel::TYPE_DISPATCHED] ?? 0;

        return $pool;
    }

    public static function getPoolConfig($poolId, $date)
    {
        $pool = LeadsPoolModel::getById($poolId);
        $counterCache = self::getPoolDailyCounterCache($poolId, $date);

        $pool['added'] = $counterCache[LeadsPoolLogModel::TYPE_ADDED] ?? 0;
        $pool['stashed'] = $counterCache[LeadsPoolLogModel::TYPE_STASHED] ?? 0;
        $pool['dispatched'] = $counterCache[LeadsPoolLogModel::TYPE_DISPATCHED] ?? 0;

        return $pool;
    }

    public static function getPoolRulesConfigs($poolId, $date)
    {
        return self::getPoolRulesCache($poolId, $date);
    }

    /**
     * 线索池当日计数器
     * pool_counter_1_20200901
     * {"1"(TYPE_ADDED): 10, "2"(TYPE_DISPATCHED): 15, "3"(TYPE_STASHED): 20}
     *
     * @param $poolId
     * @param $date
     * @return array
     */
    public static function getPoolDailyCounterCache($poolId, $date)
    {
        $redis = RedisDB::getConn();
        $key = self::getCacheKey(self::CACHE_POOL_COUNTER, $poolId, $date);
        $cache = $redis->hgetall($key);

        if (empty($cache)) {
            $cache = LeadsPoolLogModel::getDailyCount($poolId, $date);
            $redis->hmset($key, $cache);
            $redis->expire($key, self::CONFIG_EXPIRE);

            LeadsPoolLogModel::insertRecord([
                'pid' => '',
                'type' => LeadsPoolLogModel::TYPE_COUNTER_CACHE_UPDATE,
                'pool_id' => $poolId,
                'pool_type' => 0,
                'create_time' => time(),
                'date' => $date,
                'leads_student_id' => null,
                'detail' => json_encode($cache),
            ]);
        }
        return $cache;
    }

    public static function getPoolRulesCache($poolId, $date)
    {
        $redis = RedisDB::getConn();
        $key = self::getCacheKey(self::CACHE_POOL_RULES, $poolId, $date);
        $cache = $redis->get($key);

        if (empty($cache)) {
            $cache = LeadsPoolRuleModel::getRecords(['pool_id' => $poolId, 'status' => 1]);
            $jsonCache = json_encode($cache);
            $redis->setex($key, self::CONFIG_EXPIRE, $jsonCache);

            LeadsPoolLogModel::insertRecord([
                'pid' => '',
                'type' => LeadsPoolLogModel::TYPE_POOL_RULE_CACHE_UPDATE,
                'pool_id' => $poolId,
                'pool_type' => Pool::TYPE_POOL,
                'create_time' => time(),
                'date' => $date,
                'leads_student_id' => null,
                'detail' => $jsonCache,
            ]);

        } else {
            $cache = json_decode($cache, true);
        }

        return $cache;
    }

    public static function delPoolRulesCache($poolId, $date)
    {
        $redis = RedisDB::getConn();
        $key = self::getCacheKey(self::CACHE_POOL_RULES, $poolId, $date);
        $redis->del([$key]);
    }

    public static function updatePoolDailyAdded($poolId, $date, $value)
    {
        $redis = RedisDB::getConn();
        $key = self::getCacheKey(self::CACHE_POOL_COUNTER, $poolId, $date);
        $redis->hset($key, LeadsPoolLogModel::TYPE_ADDED, $value);
    }

    public static function updatePoolDailyDispatched($poolId, $date, $value)
    {
        $redis = RedisDB::getConn();
        $key = self::getCacheKey(self::CACHE_POOL_COUNTER, $poolId, $date);
        $redis->hset($key, LeadsPoolLogModel::TYPE_DISPATCHED, $value);
    }

    public static function updatePoolDailyStashed($poolId, $date, $value)
    {
        $redis = RedisDB::getConn();
        $key = self::getCacheKey(self::CACHE_POOL_COUNTER, $poolId, $date);
        $redis->hset($key, LeadsPoolLogModel::TYPE_STASHED, $value);
    }
}