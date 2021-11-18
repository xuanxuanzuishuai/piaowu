<?php
/**
 * 活动和海报库关联关系存储表
 */

namespace App\Models;

use App\Libs\RedisDB;
use App\Libs\Util;
use App\Libs\MysqlDB;

class ActivityPosterModel extends Model
{
    public static $table = 'activity_poster';

    const DISABLE_STATUS = 1; //下线
    const NORMAL_STATUS = 2; //上线

    const IS_DEL_TRUE = 1; //已删除
    const IS_DEL_FALSE = 0; //未删除

    const POSTER_ASCRIPTION_STUDENT = 0;    // 学生使用的海报
    const POSTER_ASCRIPTION_EMPLOYEE = 1;   // 员工使用的海报

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
     * @deprecated 请使用新方法 batchInsertActivityPoster
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
     * 批量写入活动海报
     * @param $activityId
     * @param $posterIdArr
     * @return bool
     */
    public static function batchInsertActivityPoster($activityId, $posterIdArr)
    {
        // 写入新的活动与海报的关系
        $activityPoster = [];
        foreach ($posterIdArr as $posterInfo) {
            $posterId = $posterInfo['poster_id'] ?? 0;
            $posterAscription = $posterInfo['poster_ascription'] ?? self::POSTER_ASCRIPTION_STUDENT;
            if (empty($posterId)) {
                return false;
            }
            $activityPoster[] = [
                'activity_id' => $activityId,
                'poster_id' => $posterId,
                'status' => self::NORMAL_STATUS,
                'poster_ascription' => $posterAscription,
            ];
        }
        if (empty($activityPoster)) {
            return false;
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
        if (empty($posterIdArr)) {
            return false;
        }
        $activityPosterList = self::getListByActivityId($activityId);
        // 没有活动需要添加
        if (empty($activityPosterList)) {
            return true;
        }
        // 传入的总数和原有总数不相等说明一定是不一样的
        if (count($activityPosterList) != count($posterIdArr)) {
            return true;
        }
        //判断是否顺序改变
        $posterIds = array_column($activityPosterList, 'poster_id');
        return !(implode(',', $posterIds) == implode(',', $posterIdArr));
    }
    
    /**
     * @param $arrActPosId
     * @param $arrActId
     * 下线活动中的海报模板,并删除缓存
     */
    public static function editPosterStatus($posId, $status)
    {
        $db = MysqlDB::getDB();
        $data = [
            'status' => $status,
        ];
        $where = [
            'poster_id' => $posId,
        ];
        $db->updateGetCount(self::$table, $data, $where);
    }
    
    /**
     * @param $posId
     * 删除缓存
     */
    public static function delRedisCache($posId)
    {
        $db = MysqlDB::getDB();
        $res = $db->select(self::$table, ['activity_id'], ['poster_id' => $posId]);
        $arrActId = array_column($res, 'activity_id');
        $redis = RedisDB::getConn();
        foreach ($arrActId as $actId) {
            $cacheKey = self::KEY_ACTIVITY_POSTER . implode('_', [$actId, self::NORMAL_STATUS, self::IS_DEL_FALSE]);
            $redis->del([$cacheKey]);
        }
    }

    /**
     * 删除海报
     * @param $activityId
     * @return bool
     */
    public static function delActivityPoster($activityId)
    {
        ActivityPosterModel::batchUpdateRecord(['is_del' => ActivityPosterModel::IS_DEL_TRUE], ['activity_id' => $activityId]);
        $cacheKey   = [];
        $activityId = is_array($activityId) ? $activityId : [$activityId];
        foreach ($activityId as $_id) {
            $cacheKey[] = self::KEY_ACTIVITY_POSTER . implode('_', [$_id, self::NORMAL_STATUS, self::IS_DEL_FALSE]);
        }
        RedisDB::getConn()->del($cacheKey);
        return true;
    }
    
    /**
     * 海报参加的活动
     * @param $posterId
     * @param $type
     * @return array|null
     */
    public static function getActivityByPidAndType($posterId, $type)
    {
        $time = time();
        $table1 = self::$table;
        $activityTable = [
            'week' => WeekActivityModel::$table,
            'month' => MonthActivityModel::$table,
            'rt' => RtActivityModel::$table,
            'real_week' => RealWeekActivityModel::$table,
            'real_month' => RealMonthActivityModel::$table,
            'invite_activity' => InviteActivityModel::$table,
        ];
        $table2 = $activityTable[$type] ?? '';
        if (empty($table2)) {
            return [];
        }
        $status11 = ActivityPosterModel::NORMAL_STATUS;
        $status12 = ActivityPosterModel::IS_DEL_FALSE;
        $status21 = OperationActivityModel::ENABLE_STATUS_OFF;
        $status22 = OperationActivityModel::ENABLE_STATUS_ON;
        $sql = "
            SELECT
                {$table1}.id,{$table1}.activity_id
            FROM
                {$table1}
                INNER JOIN {$table2} ON {$table2}.activity_id = {$table1}.activity_id
            WHERE
                {$table1}.poster_id = {$posterId}
                AND {$table1}.status = {$status11}
                AND {$table1}.is_del = {$status12}
                AND {$table2}.enable_status IN ({$status21},{$status22})
                AND {$table2}.end_time > {$time}
        ";
        $db = MysqlDB::getDB();
        $res = $db->queryAll($sql);
        return $res;
    }
}
