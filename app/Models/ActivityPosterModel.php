<?php
/**
 * 活动和海报库关联关系存储表
 */

namespace App\Models;

use App\Libs\RedisDB;
use App\Libs\Util;

class ActivityPosterModel extends Model
{
    public static $table = 'activity_poster';

    const DISABLE_STATUS = 1; //下线
    const NORMAL_STATUS = 2; //上线

    const IS_DEL_TRUE = 1; //已删除
    const IS_DEL_FALSE = 0; //未删除

    const KEY_ACTIVITY_POSTER = 'ACTIVITY_POSTER_';

    /**
     * 获取活动海报
     * @param $activityId
     * @param int $status
     * @param int $isDel
     * @param string[] $order
     * @return array
     */
    public static function getListByActivityId(
        $activityId,
        $status = self::NORMAL_STATUS,
        $isDel = self::IS_DEL_FALSE,
        $order = ['id' => 'ASC']
    ) {
        $redis = RedisDB::getConn();
        $cacheKey = self::KEY_ACTIVITY_POSTER . implode('_', [$activityId, $status, $isDel]);
        $cache = $redis->get($cacheKey);
        if (!empty($cache)) {
            return json_decode($cache, true);
        }

        $list = self::getRecords(['activity_id' => $activityId, 'status' => $status, 'is_del' => $isDel, 'ORDER' => $order]);
        if (!empty($list)) {
            $redis->setex($cacheKey, Util::TIMESTAMP_ONEDAY, json_encode($list));
        }
        return $list;
    }

    /**
     * 批量写入活动海报
     * @param $activityId
     * @param $posterIds
     * @return bool
     */
    public static function batchAddActivityPoster($activityId, $posterIds)
    {
        // 写入新的活动与海报的关系
        $activityPoster = [];
        foreach ($posterIds as $posterId) {
            $activityPoster[] = [
                'activity_id' => $activityId,
                'poster_id' => $posterId,
                'status' => self::NORMAL_STATUS,
            ];
        }
        $activityPosterRes = self::batchInsert($activityPoster);
        if (empty($activityPosterRes)) {
            return false;
        }
        return true;
    }

    /**
     * 对比活动海报是否和传入的海报相同 - 修改活动时判断海报是否有修改
     * @param $activityId
     * @param $posterIdArr
     * @return bool
     */
    public static function diffPosterChange($activityId, $posterIdArr)
    {
        if (empty($posterList)) {
            return false;
        }
        $activityPosterList = self::getListByActivityId($activityId);
        if (empty($activityPosterList)) {
            return false;
        }
        $posterIds = array_column($activityPosterList, 'poster_id');
        $diffIds = array_diff($posterIds, $posterIdArr);

        return empty($diffIds) ? false : true;
    }
}
