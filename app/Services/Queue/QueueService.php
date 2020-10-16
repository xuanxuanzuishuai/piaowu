<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/4/3
 * Time: 11:29 AM
 */

namespace App\Services\Queue;

use App\Models\EmployeeModel;
use App\Services\StudentService;
use Exception;
use App\Libs\SimpleLogger;

class QueueService
{
    //操作发起方 uc.uc_app.id
    const FROM_DSS = 10;

    /**
     * 学生第一次购买正式课包
     * @param $studentID
     * @return bool
     */
    public static function studentFirstPayNormalCourse($studentID)
    {
        try {
            $topic = new StudentSyncTopic();
            $syncData = StudentService::getStudentSyncData($studentID);
            if (empty($syncData)) {
                return false;
            }
            $topic->studentFirstPayNormalCourse($syncData[$studentID])->publish();
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $syncData ?? []);
            return false;
        }
        return true;
    }

    /**
     * 学生第一次购买付费体验课
     * @param $studentID
     * @return bool
     */
    public static function studentFirstPayTestCourse($studentID)
    {
        try {
            $topic = new StudentSyncTopic();
            $syncData = StudentService::getStudentSyncData($studentID);
            if (empty($syncData)) {
                return false;
            }
            $topic->studentFirstPayTestCourse($syncData[$studentID])->publish();
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $syncData ?? []);
            return false;
        }
        return true;
    }

    /**
     * 学员观单数据同步
     * @param $studentIDList
     * @return bool
     */
    public static function studentSyncWatchList($studentIDList)
    {
        try {
            //获取班级
            $topic = new StudentSyncTopic();
            $syncData = StudentService::getStudentSyncData($studentIDList);
            if (empty($syncData)) {
                return false;
            }
            foreach ($syncData as $sk => $sv) {
                $topic->studentSyncWatchList($sv)->publish();
            }
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $syncData ?? []);
            return false;
        }
        return true;
    }

    /**
     * 学员数据同步
     * @param $syncData
     * @return bool
     */
    public static function studentSyncData($syncData)
    {
        try {
            $topic = new StudentSyncTopic();
            $topic->studentSyncData($syncData)->publish();
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $syncData);
            return false;
        }
        return true;
    }

    /**
     * 给微信用户推送活动消息
     * @param $students
     * @param $guideWord
     * @param $shareWord
     * @param $posterUrl
     * @param $activityId
     * @param $employeeId
     * @param $activityType
     * @return bool
     */
    public static function pushWX($students, $guideWord, $shareWord, $posterUrl, $activityId, $employeeId, $activityType)
    {
        try {
            $topic = new PushMessageTopic();
            $pushTime = time();

            $count = count($students);
            if ($count > 5000) { // 超过5000条，半小时内发送完
                $deferMax = 1800;
            } elseif ($count > 1000) { // 超过1000条，10分钟内发送完
                $deferMax = 600;
            } else { // 默认2分钟内发送完
                $deferMax = 120;
            }

            foreach ($students as $student) {

                $msgBody = [
                    'student_id' => $student['user_id'],
                    'open_id' => $student['open_id'],
                    'guide_word' => $guideWord,
                    'share_word' => $shareWord,
                    'poster_url' => $posterUrl,
                    'push_wx_time' => $pushTime,
                    'activity_id' => $activityId,
                    'employee_id' => $employeeId,
                    'activity_type' => $activityType
                ];

                $topic->pushWX($msgBody)->publish(rand(0, $deferMax));
            }

        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $msgBody ?? []);
            return false;
        }
        return true;

    }

    /**
     * 发送点评
     * @param $taskId
     * @return bool
     */
    public static function pushTaskReview($taskId)
    {
        try {
            $topic = new PushMessageTopic();

            $msgBody = [
                'task_id' => $taskId,
            ];

            $topic->pushTaskReview($msgBody)->publish();

        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $msgBody ?? []);
            return false;
        }
        return true;

    }


    /**
     * 赠送时长
     * @param $uuid
     * @param $applyType
     * @param $goodsNum
     * @param $channel
     * @param int $operatorId
     * @param null $msg
     * @return bool
     */
    public static function giftDuration($uuid, $applyType, $goodsNum, $channel, $operatorId = 0, $msg = null)
    {
        try {
            $topic = new GiftCoursesTopic();
            if (!is_array($uuid)) {
                $uuid = [$uuid];
            }

            if (!empty($operatorId)) {
                $operatorName = EmployeeModel::getById($operatorId)['name'];
            } else {
                $operatorId = EmployeeModel::SYSTEM_EMPLOYEE_ID;
                $operatorName = EmployeeModel::SYSTEM_EMPLOYEE_NAME;
            }
            foreach ($uuid as $value) {
                $msgBody = [
                    'uuid' => $value,
                    'apply_type' => $applyType,
                    'goods_num' => $goodsNum,
                    'channel' => $channel,
                    'operator_id' => $operatorId,
                    'operator_name' => $operatorName,
                    'msg' => $msg
                ];
                $topic->giftDuration($msgBody)->publish();
            }

        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $msgBody ?? []);
            return false;
        }
        return true;

    }

    /**
     * 用户付费
     * @param $msgBody
     * @return bool
     */
    public static function studentPaid($msgBody = [])
    {
        try {
            $topic = new PushMessageTopic();
            $topic->studentPaid($msgBody)->publish();

        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $msgBody ?? []);
            return false;
        }
        return true;
    }

    /**
     * 新leads
     * @param $msgBody
     * @return bool
     */
    public static function newLeads($msgBody = [])
    {
        try {
            $topic = new PushMessageTopic();
            $topic->newLeads($msgBody)->publish();

        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $msgBody ?? []);
            return false;
        }
        return true;
    }

    /**
     * @param $sendArr
     * @return bool
     * 消息规则推送
     */
    public static function messageRulePushMessage($sendArr)
    {
        try {
            $topic = new PushMessageTopic();
            $pushTime = time();
            $deferMax = self::getDeferMax(count($sendArr));
            array_map(function ($i) use($topic, $deferMax, $pushTime){
                $deferMax += $i['delay_time'];
                $i['push_wx_time'] = $pushTime;
                $topic->pushRuleWx($i)->publish(rand(0, $deferMax));
            }, $sendArr);

        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $msgBody ?? []);
            return false;
        }
        return true;
    }

    /**
     * @param $sendArr
     * @param $logId
     * @param $employeeId
     * @param $activityType
     * @return bool
     * 手动push消息
     */
    public static function manualPushMessage($sendArr, $logId, $employeeId, $activityType)
    {
        try {
            $topic = new PushMessageTopic();
            $pushTime = time();
            $deferMax = self::getDeferMax(count($sendArr));
            array_map(function ($i) use($topic, $deferMax, $pushTime, $logId, $employeeId, $activityType){
                $deferMax += $i['delay_time'];
                $i['push_wx_time'] = $pushTime;
                $i['log_id'] = $logId;
                $i['activity_type'] = $activityType;
                $i['employee_id'] = $employeeId;
                $topic->pushManualRuleWx($i)->publish(rand(0, $deferMax));
            }, $sendArr);

        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $msgBody ?? []);
            return false;
        }
        return true;
    }

    private static function getDeferMax($count)
    {
        if ($count > 5000) { // 超过5000条，半小时内发送完
            $deferMax = 1800;
        } elseif ($count > 1000) { // 超过1000条，10分钟内发送完
            $deferMax = 600;
        } elseif ($count > 30) { // 2分钟内发送完
            $deferMax = 120;
        } else {
            $deferMax = 0;
        }
        return $deferMax;
    }

    /**
     * 购买正式课分配课管
     * @param $msgBody
     * @return bool
     */
    public static function courseAllotLeads($msgBody)
    {
        try {
            $topic = new PushMessageTopic();
            $topic->newLeads($msgBody, PushMessageTopic::EVENT_COURSE_MANAGE_NEW_LEADS)->publish();

        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $msgBody ?? []);
            return false;
        }
        return true;
    }
}