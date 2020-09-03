<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/07/06
 * Time: 上午10:47
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\CategoryV1Model;
use App\Models\EventTaskModel;
use App\Models\PointActivityRecordModel;
use App\Models\CheckInRecordModel;
use App\Libs\MysqlDB;
use App\Models\StudentModelForApp;
use App\Libs\Erp;


class PointActivityService
{
    //学生账户子类型
    const ACCOUNT_SUB_TYPE_STUDENT_POINTS = 3001;//学生积分余额
    //学生账户操作日志子类型
    const SOURCE_TYPE_SIGN = 1001;//每日签到
    const SOURCE_TYPE_PLAY_DURATION = 1002;//练琴时长
    const SOURCE_TYPE_EMPLOYEE_RECHARGE = 1003;//系统充值
    const SOURCE_TYPE_FINISH_TEST = 1004;//完成评测
    const SOURCE_TYPE_SHARE_TEST = 1005;//分享评测
    const SOURCE_TYPE_EXCHANGE = 2001;//兑换商品扣减
    const SOURCE_TYPE_EXPIRE_DEDUCT = 2002;//过期扣减
    const SOURCE_TYPE_EMPLOYEE_DEDUCT = 2003;//系统扣减

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
        if (empty($studentInfo)) {
            throw new RunTimeException(['student_ids_error']);
        }
        $reportData['uuid'] = $studentInfo['uuid'];
        $reportData['student_id'] = $studentId;
        $reportData['app_version'] = $params['app_version'];
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
            //双手全曲评测
            $reportData['score_final'] = $params['score_final'];
        } elseif ($activityType === CreditService::SHARE_GRADE) {
            $reportData['play_grade_id'] = $params['play_grade_id'];
        }
        //创建奖励记录
        $completeRes = CreditService::setUserCompleteTask($activityType, $reportData);
        if (empty($completeRes)) {
            return $reportRes;
        }
        //保存数据
        self::pointActivityRecord($activityType, array_keys($completeRes), $studentId, $date, $time, $reportData);
        $allAward = [];
        //同类奖章奖励同时触发只取等级最大的
        $medalAward = [];
        array_map(function ($completeVal) use (&$reportRes, &$allAward, &$medalAward) {
            $awardInfo = json_decode($completeVal, true);
            //app兼容处理
            if ($awardInfo['awards'][0]['type'] == CreditService::CREDIT_AWARD_TYPE) {
                $reportRes['amount'] = $awardInfo['awards'][0]['amount'];
                $reportRes['type'] = $awardInfo['awards'][0]['type'];
            }

            foreach ($awardInfo['awards'] as $award) {
                if ($award['type'] == CreditService::CREDIT_AWARD_TYPE) {
                    $award[] = ['amount' => $award['amount'], 'type' => $award['type']];
                } elseif ($award['type'] == CategoryV1Model::MEDAL_AWARD_TYPE) {
                    $medalInfo = MedalService::formatMedalAlertInfo($award['course_id']);
                    $medalAward[$medalInfo['category_id']][$medalInfo['medal_level'] ?: 0] = $medalInfo;
                }
            }
        }, $completeRes);
        $medalAward = [];
        array_map(function ($item)use(&$allAward){$allAward[] = end($item);} ,$medalAward);
        $reportRes['all_award'] = $allAward;
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
     * @param $appVersion
     * @return mixed
     */
    public static function getPointActivityListInfo($activityType, $studentId, $appVersion)
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
        //获取用户服务订阅状态
        $studentSubStatus = StudentServiceForApp::getSubStatus($studentId);
        //格式化模板数据
        array_walk($cacheData, function ($value, $key) use (&$formatData, $studentActivityRecord, $studentId, &$checkInTodayIsDone, &$checkInDays, $appVersion, $studentSubStatus) {
            if (!empty($value)) {
                array_map(function ($item) use ($studentActivityRecord, $key, $studentId, &$checkInTodayIsDone, &$checkInDays, $appVersion, &$formatData, $studentSubStatus) {
                    $condition = json_decode($item['condition'], true);
                    //检测可以参与活动的app最低版本号
                    $appVersionCheck = $userSubStatusCheck = true;
                    if (!empty($condition['min_version']) && (AIPlayRecordService::isOldVersionApp($appVersion, $condition['min_version']) != false)) {
                        $appVersionCheck = false;
                    }
                    //检测可以参与活动的用户有效时长资格
                    if (($condition['apply_object'] == CreditService::SUB_REMAIN_DURATION_VALID) && ($studentSubStatus === false)) {
                        $userSubStatusCheck = false;
                    }
                    if ($appVersionCheck && $userSubStatusCheck) {
                        $award = json_decode($item['award'], true);
                        $tmpInfo = [
                            'name' => $item['name'],
                            'every_day_count' => $condition['every_day_count'],
                            'continue_days' => $condition['continue_days'],
                            'award_count' => $award['awards'][0]['amount'],
                            'is_complete' => $studentActivityRecord[$key][$item['id']]['is_complete'] ?? false,
                            'complete_count' => $studentActivityRecord[$key][$item['id']]['complete_count'] ?? 0,
                        ];
                        //连续签到需要额外获取连续签到天数和今天用户是否签到
                        if ($key == CreditService::SIGN_IN_TASKS && $checkInTodayIsDone == false) {
                            $checkInDays = CheckInRecordModel::studentCheckInDays($studentId);
                            $checkInTodayIsDone = $tmpInfo['is_complete'];
                        }
                        $formatData['activity_list'][$key][] = $tmpInfo;
                    }
                }, $value);
            }
        });
        //获取总积分
        $studentAccount = self::totalPoints($studentId, self::ACCOUNT_SUB_TYPE_STUDENT_POINTS);
        $formatData['student_point_info']["points"] = $studentAccount['total_num'] ?? 0;
        $formatData['student_point_info']['check_in_continue_days'] = $checkInDays;
        $formatData['student_point_info']['check_in_today_is_done'] = $checkInTodayIsDone;
        $formatData['student_point_info']['today_start_timestamp'] = Util::getStartEndTimestamp(time())[0];
        $formatData['student_point_info']['sub_status'] = $studentSubStatus;
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
            //获取dict
            $dictMap = DictService::getTypeMap(Constants::STUDENT_ACCOUNT_LOG_OP_TYPE);
            //获取任务task的信息
            $taskIdList = array_column($studentAccountDetail['data']['logs'], 'event_task_id');
            $tasksInfo = array_column(EventTaskModel::getRecords(['id' => array_unique($taskIdList)], ['id', 'name'], false), null, 'id');
            $data['total_count'] = $studentAccountDetail['data']['total_count'];
            $data['list'] = array_map(function ($logVal) use ($tasksInfo, $dictMap) {
                $tmp = [
                    'create_time' => date("Y.m.d H:i", strtotime($logVal['create_time'])),
                    'task_name' => in_array($logVal['source_type'], [self::SOURCE_TYPE_PLAY_DURATION, self::SOURCE_TYPE_FINISH_TEST, self::SOURCE_TYPE_SHARE_TEST]) ? $tasksInfo[$logVal['event_task_id']]['name'] : $dictMap[$logVal['source_type']],
                    'award_num' => $logVal['num'],
                    'operate_type' => $logVal['operate_type'],
                ];
                return $tmp;
            }, $studentAccountDetail['data']['logs']);
        }
        return $data;
    }
}