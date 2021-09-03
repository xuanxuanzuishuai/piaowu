<?php
/**
 * erp 积分入账
 */

namespace App\Services\Queue;

class ErpStudentAccountTopic extends BaseTopic
{
    const TOPIC_NAME = "student_account";

    const EVENT_ERP_ACCOUNT_NAME_MAGIC = 'normal_credited'; //可用积分入账

    /* 入账积分类型 */
    // const POST_TYPE_NORMAL = 1; //可用积分
    // const POST_TYPE_FREEZE = 2; //冻结积分
    // const POST_TYPE_UNFREEZE = 3; //解冻积分

    /* 入账积分行为 */
    // const REGISTER_ACTION = 5001; //用户注册
    const UPLOAD_POSTER_ACTION = 5002; //付费用户上传海报截图
    // const REFEREE_ATTEND_ACTION = 5003; //转介绍被邀请人出席体验课, 邀请人获得积分
    // const FIRST_PAY_ACTION_FOR_REFEREE = 5004; //转介绍被邀请人首次付费, 被邀请人获得积分
    // const FIRST_PAY_ACTION_FOR_REFERRER = 5005; //转介绍被邀请人首次付费, 邀请人获得积分
    // const ATTEND_TWO_NORMAL_SCHEDULE_ACTION_FOR_REFEREE = 5006; //转介绍被邀请人完成两节课, 被邀请人获得积分
    // const ATTEND_TWO_NORMAL_SCHEDULE_ACTION_FOR_REFERRER = 5007; //转介绍被邀请人完成两节课, 邀请人获得积分

    /**
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 积分入账
     * @param $data
     * @return $this
     */
    public function erpNormalCredited($data)
    {
        $this->setEventType(self::EVENT_ERP_ACCOUNT_NAME_MAGIC);
        $this->setMsgBody($data);
        return $this;
    }
}
