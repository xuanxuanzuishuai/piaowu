<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/4/3
 * Time: 11:42 AM
 */

namespace App\Services\Queue;

class StudentStatusTopic extends BaseTopic
{
    const TOPIC_NAME = "student_sync";
    // 数据同步
    const STUDENT_SYNC_DATA = "student_sync_data";
    // 学生第一次购买正式课包
    const STUDENT_FIRST_PAY_NORMAL_COURSE = "student_first_pay_normal_course";
    // 学生第一次购买付费体验课
    const STUDENT_FIRST_PAY_TEST_COURSE = "student_first_pay_test_course";
    // 学生观单数据同步
    const STUDENT_WATCH_LIST = "student_watch_list";

    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 学生第一次购买正式课包
     * @param $syncData
     * @return $this
     */
    public function studentFirstPayNormalCourse($syncData)
    {
        //设置事件类型
        $this->setEventType(self::STUDENT_FIRST_PAY_NORMAL_COURSE);
        //设置消息体
        $this->setMsgBody($syncData);
        return $this;
    }

    /**
     * 学生第一次购买付费体验课
     * @param $syncData
     * @return $this
     */
    public function studentFirstPayTestCourse($syncData)
    {
        //设置事件类型
        $this->setEventType(self::STUDENT_FIRST_PAY_TEST_COURSE);
        //设置消息体
        $this->setMsgBody($syncData);
        return $this;
    }

    /**
     * 学员观单数据同步
     * @param $syncData
     * @return $this
     */
    public function studentSyncWatchList($syncData)
    {
        //设置事件类型
        $this->setEventType(self::STUDENT_WATCH_LIST);
        //设置消息体
        $this->setMsgBody($syncData);
        return $this;
    }

    /**
     * 学员数据同步
     * @param $syncData
     * @return $this
     */
    public function studentSyncData($syncData)
    {
        $this->setEventType(self::STUDENT_SYNC_DATA);
        $this->setMsgBody($syncData);
        return $this;
    }
}