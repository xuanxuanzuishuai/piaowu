<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/6/28
 * Time: 下午4:52
 */

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\RedisDB;
use App\Libs\Util;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;

class OperationActivityModel extends Model
{
    public static $table = "operation_activity";

    const KEY_CURRENT_ACTIVE = 'CURRENT_ACTIVE_ACTIVITY_';
    const ACTIVITY_CACHE_EXPIRE = 86400;

    // 启用状态
    const ENABLE_STATUS_OFF = 1;        // 待启用
    const ENABLE_STATUS_ON = 2;         // 启用
    const ENABLE_STATUS_DISABLE = 3;    // 已禁用

    // 亲友优惠券活动类型
    const RULE_TYPE_COURSE = 2; // 课管活动
    const RULE_TYPE_ASSISTANT = 1; // 社群活动

    // 转介绍运营活动类型
    const TYPE_MONTH_ACTIVITY = 1;//月月有奖
    const TYPE_WEEK_ACTIVITY = 2;//周周领奖

    const TIME_STATUS_PENDING = 1;
    const TIME_STATUS_ONGOING = 2;
    const TIME_STATUS_FINISHED = 3;


    /**
     * 当前阶段为付费正式课且未参加当前活动的学员手微信open_id
     * @param $activityId
     * @return array
     */
    public static function getPaidAndNotAttendStudentsOpenid($activityId)
    {
        // 当前阶段为付费正式课且未参加当前活动的学员
        $boundUsers = MysqlDB::getDB()->queryAll("
SELECT
    uw.user_id, uw.open_id
FROM
    " . DssStudentModel::$table . " s
INNER JOIN
    " . DssUserWeiXinModel::$table . " uw ON uw.user_id = s.id
WHERE
    s.has_review_course = " . DssStudentModel::REVIEW_COURSE_1980 . "
    AND s.status = " . DssStudentModel::STATUS_NORMAL . "
    AND s.id NOT IN (SELECT student_id FROM " . SharePosterModel::$table . " WHERE activity_id = :activity_id)
    AND uw.user_type = " . DssUserWeiXinModel::USER_TYPE_STUDENT . "
    AND uw.status = " . DssUserWeiXinModel::STATUS_NORMAL . "
    AND uw.app_id = " . Constants::SMART_APP_ID, [':activity_id' => $activityId]);

        return $boundUsers ?? [];
    }

    /**
     * 获取当前生效的活动
     * @param $posterType
     * @param int $id
     * @return array|mixed|null
     */
    public static function getActiveActivity($posterType, $id = 0)
    {
        $now = time();
        if (!empty($id)) {
            if ($posterType == TemplatePosterModel::INDIVIDUALITY_POSTER) {
                return MonthActivityModel::getRecord(['activity_id' => $id]);
            }
            return WeekActivityModel::getRecord(['activity_id' => $id]);
        }

        $redis = RedisDB::getConn();
        $cacheKey = OperationActivityModel::KEY_CURRENT_ACTIVE . $posterType;
        $cache = $redis->get($cacheKey);
        if (!empty($cache)) {
            return json_decode($cache, true) ?: [];
        }
        $allActive = [];
        if ($posterType == TemplatePosterModel::INDIVIDUALITY_POSTER) {
            $allActive = MonthActivityModel::getRecords(['enable_status' => OperationActivityModel::ENABLE_STATUS_ON]);
        }
        if ($posterType == TemplatePosterModel::STANDARD_POSTER) {
            $allActive = WeekActivityModel::getRecords([
                'enable_status' => OperationActivityModel::ENABLE_STATUS_ON,
                'start_time[<]' => $now,
                'end_time[>]' => $now
            ]);
        }
        if (empty($allActive)) {
            return [];
        }

        $activity = [];
        foreach ($allActive as $item) {
            if ($item['start_time'] < $now && $item['end_time'] > $now) {
                $activity = $item;
                break;
            }
        }
        if (!empty($activity)) {
            // 获取过期时间
            $expireSecond = Util::getDayFirstSecondUnix(date("Y-m-d", strtotime("+1 day")));
            $expireSecond = $expireSecond - time();
            $redis->setex($cacheKey, $expireSecond, json_encode($activity));
        }
        return $activity;
    }

    /**
     * 检查活动某个时间点是否是启用状态
     * @param $activityInfo
     * @param int $time
     * @return bool
     */
    public static function checkActivityEnableStatusOn($activityInfo, int $time = 0)
    {
        $time = !empty($time) ? $time : time();
        if ($activityInfo['enable_status'] == self::ENABLE_STATUS_ON && $activityInfo['start_time'] <= $time && $activityInfo['end_time'] >= $time) {
            return true;
        }
        return false;
    }
}
