<?php
/**
 * 活动扩展表
 */

namespace App\Models;

use App\Libs\RedisDB;
use App\Libs\Util;

class ActivityExtModel extends Model
{
    public static $table = 'activity_ext';
    const KEY_ACTIVITY_EXT = 'ACTIVITY_EXT_';

    /**
     * 获取活动扩展信息
     * @param $activityId
     * @return array|mixed
     */
    public static function getActivityExt($activityId)
    {
        if (empty($activityId)) {
            return [];
        }
        $cacheKey = self::KEY_ACTIVITY_EXT . $activityId;
        $redis = RedisDB::getConn();
        $cache = $redis->get($cacheKey);
        if (!empty($cache)) {
            $cache = json_decode($cache, true);
            $cache['award_rule'] = Util::textDecode($cache['award_rule']);
            return $cache;
        }

        $record = self::getRecord(['activity_id' => $activityId]);
        if (!empty($record)) {
            $redis->setex($cacheKey, Util::TIMESTAMP_ONEDAY, json_encode($record));
        }
        if (!empty($record['award_rule'])) {
            $record['award_rule'] = Util::textDecode($record['award_rule']);
        }
        return $record;
    }
}