<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/6/28
 * Time: 下午4:52
 */

namespace App\Models;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\RedisDB;
use App\Libs\Util;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Erp\ErpStudentModel;

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
    const TYPE_INVITE_ACTIVITY = 3;//邀请领奖

    // 活动时间状态
    const TIME_STATUS_PENDING = 1;//待开始
    const TIME_STATUS_ONGOING = 2;//进行中
    const TIME_STATUS_FINISHED = 3;//已结束
    //活动前端展示状态于活动不同状态的映射关系
    const ACTIVITY_SHOW_STATUS_MAP_ENABLE_AND_TIME_STATUS = [
        1 => self::ENABLE_STATUS_OFF,
        2 => self::ENABLE_STATUS_ON,
        3 => self::ENABLE_STATUS_DISABLE,
        4 => self::TIME_STATUS_PENDING,
        5 => self::TIME_STATUS_ONGOING,
        6 => self::TIME_STATUS_FINISHED,
    ];


    // 活动奖品发奖方式类型:1立即发放 2延时发放
    const AWARD_PRIZE_TYPE_IN_TIME = 1;
    const AWARD_PRIZE_TYPE_DELAY = 2;

    // 活动投放地区
    const ACTIVITY_COUNTRY_ALL = 0; // 所有地区
    const ACTIVITY_COUNTRY_CN = 86; // 中国
    const ACTIVITY_COUNTRY_EN = 1;  // 所有非中国地区

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

        // $redis = RedisDB::getConn();
        // $cacheKey = OperationActivityModel::KEY_CURRENT_ACTIVE . $posterType;
        // $cache = $redis->get($cacheKey);
        // if (!empty($cache)) {
        //     return json_decode($cache, true) ?: [];
        // }
        $allActive = [];
        if ($posterType == TemplatePosterModel::INDIVIDUALITY_POSTER) {
            $allActive = MonthActivityModel::getRecords(['enable_status' => OperationActivityModel::ENABLE_STATUS_ON]);
        }
        if ($posterType == TemplatePosterModel::STANDARD_POSTER) {
            $allActive = WeekActivityModel::getRecords([
                'enable_status' => OperationActivityModel::ENABLE_STATUS_ON,
                'start_time[<]' => $now,
                'end_time[>]'   => $now
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
        // if (!empty($activity)) {
        //     // 获取过期时间
        //     $expireSecond = Util::getDayFirstSecondUnix(date("Y-m-d", strtotime("+1 day")));
        //     $expireSecond = $expireSecond - time();
        //     $redis->setex($cacheKey, $expireSecond, json_encode($activity));
        // }
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

    /**
     * 活动时间状态和sql的查询条件映射
     * @param $timeStatus
     * @return array
     */
    public static function timeStatusMapToSqlWhere($timeStatus)
    {
        $where = [];
        $time = time();
        switch ($timeStatus) {
            case self::TIME_STATUS_PENDING://待开始
                $where['start_time[>]'] = $time;
                break;
            case self::TIME_STATUS_ONGOING://进行中
                $where['start_time[<=]'] = $time;
                $where['end_time[>=]'] = $time;
                break;
            case self::TIME_STATUS_FINISHED://已结束
                $where['end_time[<]'] = $time;
                break;
            default:
                return $where;
        }
        return $where;
    }

    /**
     * 活动时间状态
     * @param $startTime
     * @param $endTime
     * @return int
     */
    public static function dataMapToTimeStatus($startTime, $endTime)
    {
        $time = time();
        if ($startTime <= $time && $endTime >= $time) {
            $timeStatus = self::TIME_STATUS_ONGOING;
        } elseif ($startTime > $time) {
            $timeStatus = self::TIME_STATUS_PENDING;
        } else {
            $timeStatus = self::TIME_STATUS_FINISHED;
        }
        return $timeStatus;
    }

    /**
     * 获取学生周周领奖活动投放区域
     * @param $studentInfo
     * @param int $appId
     * @return array
     */
    public static function getStudentWeekActivityCountryCode($studentInfo, int $appId = Constants::SMART_APP_ID): array
    {
        $studentId = $studentInfo['id'] ?? ($studentInfo['student_id'] ?? 0);
        // 如果学生信息中不存在国家代码，则去查询信息
        if ($appId == Constants::REAL_APP_ID) {
            $studentCountryCode = $studentInfo['country_code'] ?? ErpStudentModel::getStudentInfoById($studentId)['country_code'];
        } else {
            $studentCountryCode = $studentInfo['country_code'] ?? DssStudentModel::getRecord(['id' => $studentId],
                    ['country_code'])['country_code'];
        }
        return self::getWeekActivityCountryCode($studentCountryCode);
    }

    /**
     * 获取周周领奖活动投放区域
     * @param $activityCountryCode
     * @return array
     */
    public static function getWeekActivityCountryCode($activityCountryCode): array
    {
        if ($activityCountryCode == self::ACTIVITY_COUNTRY_CN) {
            // 国内+全球的标识
            $studentAllowJoinActivityCountryCode = [self::ACTIVITY_COUNTRY_ALL, self::ACTIVITY_COUNTRY_CN];
        } elseif (!empty($activityCountryCode)) {
            // 国外+全球的标识
            $studentAllowJoinActivityCountryCode = [self::ACTIVITY_COUNTRY_ALL, self::ACTIVITY_COUNTRY_EN];
        } else {
            // 如果没有国家代码，只返回全球标识
            $studentAllowJoinActivityCountryCode = [self::ACTIVITY_COUNTRY_ALL];
        }
        return $studentAllowJoinActivityCountryCode;
    }

    /**
     * 检查活动是不是指定的country_code
     * @param $studentInfo
     * @param $activityInfo
     * @param int $appId
     * @return bool
     * @throws RunTimeException
     */
    public static function checkWeekActivityCountryCode(
        $studentInfo,
        $activityInfo,
        int $appId = Constants::SMART_APP_ID
    ) {
        if (!isset($activityInfo['activity_country_code'])) {
            throw new RunTimeException(['week_activity_student_cannot_upload']);
        }
        // 先查是否指定的uuid
        if ($appId == Constants::SMART_APP_ID) {
            $info = SharePosterDesignateUuidModel::getRecord(['uuid' => $studentInfo['uuid']]);
            if (!empty($info)) {
                return true;
            }
        }
        $studentAllowJoinActivityCountryCode = self::getStudentWeekActivityCountryCode($studentInfo, $appId);
        if (!in_array($activityInfo['activity_country_code'], $studentAllowJoinActivityCountryCode)) {
            throw new RunTimeException(['week_activity_student_cannot_upload']);
        }
        return true;
    }

    /**
     * 活动展示状态于查询条件where转换
     * @param $showStatus
     * @return array
     */
    public static function showStatusMapWhere($showStatus): array
    {
        //启用状态使用时间状态进行替换，放便后台管理人员使用
        switch ($showStatus) {
            case 1:
            case 3:
                //启用状态
                $where['status'] = $showStatus;
                break;
            case 4:
            case 5:
            case 6:
                //时间状态
                $where = OperationActivityModel::timeStatusMapToSqlWhere(OperationActivityModel::ACTIVITY_SHOW_STATUS_MAP_ENABLE_AND_TIME_STATUS[$showStatus]);
                break;
            default:
                return [];
        }
        return $where;
    }
}
