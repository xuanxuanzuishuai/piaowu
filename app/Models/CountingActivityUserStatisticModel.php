<?php
/**
 * 学生全局参与数据统计
 * User: liz
 * Date: 2021/7/13
 * Time: 10:35 AM
 */

namespace App\Models;

use App\Libs\Constants;
use App\Libs\RedisDB;
use App\Libs\Util;

class CountingActivityUserStatisticModel extends Model
{
    const KEY_USER_STATISTIC = 'counting_activity_user_statistic_';

    public static $table = 'counting_activity_user_statistic';

    /**
     * 查询全局数据
     * @param array $allIds
     * @return array
     */
    public static function getUserData($allIds = [])
    {
        if (empty($allIds)) {
            return [];
        }
        // 查询缓存
        // 查询数据库，根据update字段决定是否需要更新
        $allIds = array_unique($allIds);
        $queryIds = [];
        $resData = [];
        $redis = RedisDB::getConn();
        $setCacheData = function ($id, $totalNums, $conNums) use ($redis) {
            $key = self::KEY_USER_STATISTIC . $id;
            $redis->hset($key, 'cumulative_nums', $totalNums);
            $redis->hset($key, 'continue_nums', $conNums);
            $redis->expire($key, Util::TIMESTAMP_ONEWEEK);
        };
        foreach ($allIds as $id) {
            $totalNums = $redis->hget(self::KEY_USER_STATISTIC . $id, 'cumulative_nums');
            $conNums = $redis->hget(self::KEY_USER_STATISTIC . $id, 'continue_nums');
            if (empty($totalNums)) {
                $queryIds[$id] = $id;
            } else {
                $resData[$id]['cumulative_nums'] = $totalNums;
            }
            $resData[$id]['continue_nums'] = $conNums ?? 0;
        }

        // 数据是否已存在
        $exists = [];
        if (!empty($queryIds)) {
            $data = self::getRecords(['student_id' => $queryIds]);
            foreach ($data as $item) {
                if (empty($item['update'])) {
                    $resData[$item['student_id']] = $item;
                    unset($queryIds[$item['student_id']]);
                    $setCacheData($item['student_id'], $item['cumulative_nums'], $item['continue_nums']);
                } else {
                    $exists[$item['student_id']] = $item['id'];
                }
            }
        }
        if (!empty($queryIds)) {
            // 查询所有有效活动
            // 查询用户在这些活动中所有已通过的数据
            // 统计
            $allActivities = WeekActivityModel::getRecords(
                [
                    'enable_status' => OperationActivityModel::ENABLE_STATUS_ON,
                    'ORDER' => ['id' => 'DESC']
                ],
                [
                    'activity_id'
                ]
            );
            $allActivitiesIds = array_column($allActivities, 'activity_id');
            $where = [
                'activity_id' => $allActivitiesIds,
                'user_id' => $queryIds,
                'poster_status' => SharePosterModel::VERIFY_STATUS_QUALIFIED,
                'no_limit' => Constants::STATUS_TRUE,
            ];
            $posterList = SharePosterModel::getWeekPosterList($where);
            $posterList = $posterList[0] ?? [];
            $userData = [];
            foreach ($posterList as $item) {
                $userData[$item['student_id']][$item['activity_id']] = $item;
            }
            $insertData = [];
            $updateData = [];
            foreach ($userData as $studentId => $item) {
                $conNums = 0;
                $totalNums = count($item);
                foreach ($allActivitiesIds as $activityId) {
                    if (in_array($activityId, array_column($item, 'activity_id'))) {
                        if (empty($saveData[$studentId]['continue_nums'])) {
                            $conNums += 1;
                        }
                    } else {
                        $saveData[$studentId]['continue_nums'] = $conNums;
                        break 1;
                    }
                }
                if (isset($exists[$studentId])) {
                    $updateData[$exists[$studentId]] = [
                        'cumulative_nums' => $totalNums,
                        'continue_nums' => $conNums,
                        'update' => Constants::STATUS_FALSE,
                    ];
                } else {
                    $insertData[] = [
                        'student_id'      => $studentId,
                        'continue_nums'   => $conNums,
                        'cumulative_nums' => $totalNums,
                    ];
                }
                $resData[$studentId]['cumulative_nums'] = $totalNums;
                $resData[$studentId]['continue_nums'] = $conNums;
                $setCacheData($studentId, $totalNums, $conNums);
            }
            if (!empty($insertData)) {
                self::batchInsert($insertData);
            }
            if (!empty($updateData)) {
                foreach ($updateData as $id => $d) {
                    self::updateRecord($id, $d);
                }
            }
        }
        return $resData;
    }

    /**
     * @param int $studentId
     * @return false|int|null
     */
    public static function setUpdateFlag($studentId = 0)
    {
        if (empty($userId)) {
            return false;
        }
        return self::batchUpdateRecord(['update' => Constants::STATUS_TRUE], ['student_id' => $studentId]);
    }
}

