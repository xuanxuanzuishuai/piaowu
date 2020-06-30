<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/4/3
 * Time: 11:29 AM
 */

namespace App\Services\Queue;

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
     * @return bool
     */
    public static function pushWX($students, $guideWord, $shareWord, $posterUrl, $activityId, $employeeId)
    {
        try {
            $topic = new PushMessageTopic();
            $pushTime = time();

            foreach ($students as $student) {

                $msgBody = [
                    'student_id' => $student['user_id'],
                    'open_id' => $student['open_id'],
                    'guide_word' => $guideWord,
                    'share_word' => $shareWord,
                    'poster_url' => $posterUrl,
                    'push_wx_time' => $pushTime,
                    'activity_id' => $activityId,
                    'employee_id' => $employeeId
                ];

                $topic->pushWX($msgBody)->publish();
            }

        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $msgBody ?? []);
            return false;
        }
        return true;

    }

    /**
     * 赠送时长
     * @param $uuid
     * @param $courseId
     * @param $courseNum
     * @return bool
     */
    public static function giftCourses($uuid, $courseId, $courseNum)
    {
        try {
            $topic = new GiftCoursesTopic();

            $msgBody = [
                'uuid' => $uuid,
                'course_id' => $courseId,
                'course_num' => $courseNum,
            ];

            $topic->activityGift($msgBody)->publish();

        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $msgBody ?? []);
            return false;
        }
        return true;

    }
}