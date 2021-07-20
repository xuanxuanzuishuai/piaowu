<?php
/**
 * 学生全局参与数据统计
 * User: liz
 * Date: 2021/7/13
 * Time: 10:35 AM
 */

namespace App\Models;

use App\Libs\Constants;

class CountingActivityUserStatisticModel extends Model
{
    public static $table = 'counting_activity_user_statistic';

    /**
     * 更新用户数据
     * @param array $userIds
     * @return array
     */
    public static function updateUserData($userIds = [])
    {
        if (empty($userIds)) {
            return [];
        }
        // 数据是否已存在
        $exists = [];
        $data = self::getRecords(['student_id' => $userIds]);
        foreach ($data as $item) {
            $exists[$item['student_id']] = $item['id'];
        }
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
            'user_id' => $userIds,
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
        $resData = [];
        // 计算用户数据
        foreach ($userData as $studentId => $item) {
            $conNums = 0;
            $totalNums = count($item);
            $userActivities = array_column($item, 'activity_id');
            foreach ($allActivitiesIds as $activityId) {
                if (in_array($activityId, $userActivities)) {
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
        }
        // 默认值
        foreach ($userIds as $uid) {
            if (!isset($resData[$uid])) {
                $resData[$uid]['cumulative_nums'] = 0;
                $resData[$uid]['continue_nums'] = 0;
            }
            if (!isset($exists[$uid])) {
                $insertData[] = [
                    'student_id'      => $uid,
                    'continue_nums'   => 0,
                    'cumulative_nums' => 0,
                ];
            }
        }
        if (!empty($insertData)) {
            self::batchInsert($insertData);
        }
        if (!empty($updateData)) {
            foreach ($updateData as $id => $d) {
                self::updateRecord($id, $d);
            }
        }
        return $resData;
    }
}
