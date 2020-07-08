<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/07/06
 * Time: 上午10:47
 */

namespace App\Services;


use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\PointActivityRecordModel;
use App\Models\CheckInRecordModel;
use App\Libs\MysqlDB;
use App\Models\StudentModelForApp;
use App\Libs\Erp;


class PointActivityService
{
    //学生账户子类型
    const ACCOUNT_SUB_TYPE_STUDENT_POINTS = 3001;//学生积分余额

    /**
     * 积分活动数据上报
     * @param $activityType
     * @param $studentId
     * @param array $params
     * @return array
     * @throws RunTimeException
     */
    public static function reportRecord($activityType, $studentId, $params = [])
    {
        $time = time();
        $date = date("Y-m-d");
        //获取学生信息
        $studentInfo = StudentModelForApp::getById($studentId);
        $reportData['uuid'] = $studentInfo['uuid'];
        $reportData['student_id'] = $studentId;
        $reportRes = [
            'amount' => 0,
        ];
        if ($activityType === CreditService::SIGN_IN_TASKS) {
            //连续签到
            $reportData['continue_days'] = CheckInRecordModel::studentCheckInDays($studentId) + 1;
        } elseif ($activityType === CreditService::PLAY_PIANO_TASKS) {
            //练琴活动:
            $reportData['play_duration'] = $params['play_duration'];
        } elseif ($activityType === CreditService::BOTH_HAND_EVALUATE) {
            //双手评测:暂无参数传入，占位
            //todo
        } elseif ($activityType === CreditService::SHARE_GRADE) {
            //分享成绩:暂无参数传入，占位
            //todo
        } else {
            throw new RunTimeException(['not_support_type']);
        }
        //创建奖励记录
        $completeRes = CreditService::setUserCompleteTask($activityType, $reportData);
        if (empty($completeRes)) {
            return $reportRes;
        }
        //保存数据
        self::pointActivityRecord($activityType, array_keys($completeRes), $studentId, $date, $time, $params);
        array_map(function ($completeVal) use (&$reportRes) {
            $awardInfo = json_decode($completeVal, true);
            $reportRes['amount'] = $awardInfo['awards'][0]['amount'];
            $reportRes['type'] = $awardInfo['awards'][0]['type'];
        }, $completeRes);
        return $reportRes;
    }

    /**
     * 记录每日签到数据
     * @param $activityType
     * @param $taskIdList
     * @param $studentId
     * @param $date
     * @param $time
     * @param $params
     * @return bool
     * @throws RunTimeException
     */
    public static function pointActivityRecord($activityType, $taskIdList, $studentId, $date, $time, $params)
    {
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        if ($activityType === CreditService::SIGN_IN_TASKS) {
            //查询学生是否存在签到记录
            $checkInRecord = CheckInRecordModel::getRecord(['student_id' => $studentId, 'type' => CheckInRecordModel::CONTINUE_CHECK_IN_ACTION_TYPE], ['id', 'last_date'], false);
            if (empty($checkInRecord)) {
                $insertData = [
                    'student_id' => $studentId,
                    'type' => CheckInRecordModel::CONTINUE_CHECK_IN_ACTION_TYPE,
                    'last_date' => $date,
                ];
                $recordAffectRows = CheckInRecordModel::insertRecord($insertData, false);
            } elseif ($checkInRecord['last_date'] != $date) {
                $recordAffectRows = CheckInRecordModel::updateRecord($checkInRecord['id'], ['days' => $params['continue_days'], 'last_date' => $date], false);
            }
            if (empty($recordAffectRows)) {
                $db->rollBack();
                throw new RunTimeException(['add_check_in_record_fail']);
            }
        }
        //记录上报数据详情
        $recordInsertData = array_map(function ($taskId) use ($studentId, $date, $time) {
            $tmpData = [
                'student_id' => $studentId,
                'task_id' => $taskId,
                'report_date' => $date,
                'create_time' => $time,
                'update_time' => $time,
            ];
            return $tmpData;
        }, $taskIdList);
        $insertRes = PointActivityRecordModel::batchInsert($recordInsertData, false);
        if ($insertRes === false) {
            $db->rollBack();
            throw new RunTimeException(['add_point_activity_record_fail']);
        }
        $db->commit();
        return true;
    }


    /**
     * 获取积分活动列表
     * @param $studentId
     * @param $activityType
     * @return mixed
     */
    public static function getPointActivityListInfo($activityType, $studentId)
    {
        //模拟获取缓存数据
        $formatData = [
            'student_point_info' => [],
            'activity_list' => [],
        ];
        $cacheData = $studentActivityRecord = [];
        $checkInTodayIsDone = false;
        $checkInDays = 0;
        $tmpCacheData = CreditService::getActivityTemplate($activityType);
        if (empty($tmpCacheData)) {
            return $formatData;
        }
        if (!empty($activityType)) {
            $cacheData[$activityType] = $tmpCacheData;
        } else {
            $cacheData = $tmpCacheData;
        }
        //获取当前用户参与活动记录
        $tmpRecord = CreditService::getUserCompleteStatus($studentId, $activityType);
        if (!empty($activityType)) {
            $studentActivityRecord[$activityType] = $tmpRecord;
        } else {
            $studentActivityRecord = $tmpRecord;
        }
        //格式化模板数据
        array_walk($cacheData, function ($value, $key) use (&$formatData, $studentActivityRecord, $studentId, &$checkInTodayIsDone, &$checkInDays) {
            if (!empty($value)) {
                $formatData['activity_list'][$key] = array_map(function ($item) use ($studentActivityRecord, $key, $studentId, &$checkInTodayIsDone, &$checkInDays) {
                    $condition = json_decode($item['condition'], true);
                    $award = json_decode($item['award'], true);
                    $tmpInfo = [
                        'name' => $item['name'],
                        'every_day_count' => $condition['every_day_count'],
                        'continue_days' => $condition['continue_days'],
                        'award_count' => $award['awards'][0]['amount'],
                        'is_complete' => $studentActivityRecord[$key][$item['id']]['is_complete'] ?? false,
                    ];
                    //连续签到需要额外获取连续签到天数和今天用户是否签到
                    if ($key == CreditService::SIGN_IN_TASKS && $checkInTodayIsDone == false) {
                        $checkInDays = CheckInRecordModel::studentCheckInDays($studentId);
                        $checkInTodayIsDone = $tmpInfo['is_complete'];
                    }
                    return $tmpInfo;
                }, $value);
            }
        });
        //获取总积分
        $studentAccount = self::totalPoints($studentId, self::ACCOUNT_SUB_TYPE_STUDENT_POINTS);
        $formatData['student_point_info']["points"] = $studentAccount['total_num'] ?? 0;
        $formatData['student_point_info']['check_in_continue_days'] = $checkInDays;
        $formatData['student_point_info']['check_in_today_is_done'] = $checkInTodayIsDone;
        $formatData['student_point_info']['today_start_timestamp'] = Util::getStartEndTimestamp(time())[0];
        return $formatData;
    }


    /**
     * 获取学生积分
     * @param $studentId
     * @param $subType
     * @return array
     */
    public static function totalPoints($studentId, $subType)
    {
        //获取学生信息
        $studentInfo = StudentModelForApp::getById($studentId);
        //请求erp方法
        $erp = new Erp();
        $studentAccount = $erp->studentAccount($studentInfo['uuid']);
        $points = [];
        if (empty($studentAccount['code']) && !empty($studentAccount['data'])) {
            $points = array_column($studentAccount['data'], null, 'sub_type')[$subType];
        }
        return $points;
    }


    /**
     * 获取学生积分明细列表
     * @param $studentId
     * @param $subType
     * @param $page
     * @param $count
     * @return array
     */
    public static function pointsDetail($studentId, $page = 1, $count = 20, $subType = self::ACCOUNT_SUB_TYPE_STUDENT_POINTS)
    {
        //获取学生信息
        $studentInfo = StudentModelForApp::getById($studentId);
        //请求erp方法
        $erp = new Erp();
        //获取三十天之内的数据
        $studentAccountDetail = $erp->studentAccountDetail($studentInfo['uuid'], $subType, $page, $count, strtotime("-30 day", Util::getStartEndTimestamp(time())[0]));
        $data = [
            'points' => self::totalPoints($studentId, self::ACCOUNT_SUB_TYPE_STUDENT_POINTS)['total_num'] ?? 0,
            'total_count' => 0,
            'list' => [],
        ];
        if (empty($studentAccountDetail['code']) && $studentAccountDetail['data']['total_count'] > 0) {
            $data['total_count'] = $studentAccountDetail['data']['total_count'];
            $data['list'] = array_map(function ($logVal) {
                $tmp = [
                    'create_time' => date("Y.m.d H:i", strtotime($logVal['create_time'])),
                    'task_name' => $logVal['remark'],
                    'award_num' => $logVal['num'],
                ];
                return $tmp;
            }, $studentAccountDetail['data']['logs']);
        }
        return $data;
    }
}