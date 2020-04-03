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
    //操作发起方
    const FROM_DSS = 5;

    /**
     * 学生第一次购买正式课包
     * @param $studentID
     * @return bool
     */
    public static function studentFirstPayNormalCourse($studentID)
    {
        try {
            $topic = new StudentStatusTopic();
            $syncData = StudentService::getStudentSyncData($studentID);
            if (empty($syncData)) {
                return false;
            }
            $topic->studentFirstPayNormalCourse($syncData[$studentID])->publish();
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $syncData[$studentID]);
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
            $topic = new StudentStatusTopic();
            $syncData = StudentService::getStudentSyncData($studentID);
            if (empty($syncData)) {
                return false;
            }
            $topic->studentFirstPayTestCourse($syncData[$studentID])->publish();
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $syncData[$studentID]);
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
            $topic = new StudentStatusTopic();
            $syncData = StudentService::getStudentSyncData($studentIDList);
            if (empty($syncData)) {
                return false;
            }
            foreach ($syncData as $sk => $sv) {
                $topic->studentSyncWatchList($sv)->publish();
            }
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $syncData);
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
            $topic = new StudentStatusTopic();
            $topic->studentSyncData($syncData)->publish();
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $syncData);
            return false;
        }
        return true;
    }
}