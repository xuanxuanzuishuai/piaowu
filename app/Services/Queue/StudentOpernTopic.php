<?php
/**
 * Created by PhpStorm.
 * User: xingkuiYu
 * Date: 2021/6/15
 * Time: 10:48
 */

namespace App\Services\Queue;

class StudentOpernTopic extends BaseTopic
{
    const TOPIC_NAME = "common_push";

    const EVENT_TYPE_UPDATE_STUDENT_INFO = 'update_student_name_and_collect';

    /**
     * ThirdPartBillTopic constructor.
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 推学生信息
     * @param array $data
     * @return $this
     */
    public function pushStudentInfo(array $data)
    {
        $this->setEventType(self::EVENT_TYPE_UPDATE_STUDENT_INFO);
        $this->setMsgBody($data);
        return $this;
    }
}