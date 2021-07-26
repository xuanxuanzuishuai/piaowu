<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/5/8
 * Time: 5:45 PM
 */

namespace App\Services\Queue;


class StudentActivity extends BaseTopic
{
    const TOPIC_NAME = "student_activity";
    const EDIT_QUALIFIED = 'edit_qualified';
    const SIGN_UP = 'sign_up';

    /**
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 发送红包
     * @param $data
     * @return $this
     */
    public function editQualified($data)
    {
        $this->setEventType(self::EDIT_QUALIFIED);
        $this->setMsgBody($data);
        return $this;
    }

    public function signUp($data)
    {
        $this->setEventType(self::SIGN_UP);
        $this->setMsgBody($data);
        return $this;
    }


}