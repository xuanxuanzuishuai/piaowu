<?php

namespace App\Services\Queue;

use Exception;

class SaBpDataTopic extends BaseTopic
{
    const TOPIC_NAME = "sa_bp_data";

    const EVENT_POSTER_PUSH = 'ai_server_poster_push';  //海报主动推送
    const EVENT_ASSISTANT_SMS = 'ai_server_push_assistant'; // 给助教推送学员短信
	const EVENT_UPDATE_USER_PROFILE = 'update_user_profile'; //神策用户属性
    const EVENT_REAL_USER_SHARE_POSTER_VERIFY_STATUS = 'pa_share_pass_detail'; // 真人分享有奖活动审核情况
    const EVENT_AI_USER_SHARE_POSTER_VERIFY_STATUS   = 'ai_share_pass_detail'; // 智能分享有奖活动审核情况
    const EVENT_QC_USER_SHARE_POSTER_VERIFY_STATUS   = 'qc_share_pass_detail'; // 清晨分享有奖活动审核情况

    /**
     * SaBpDataTopic constructor.
     * @param null $publishTime
     * @param int $isClusterModel
     * @throws Exception
     */
	public function __construct($publishTime = null, $isClusterModel = self::SINGLE_NSQ)
	{
		parent::__construct(self::TOPIC_NAME, $publishTime, QueueService::FROM_OP, $isClusterModel);
	}

    /**
     * 后台主动推送海报
     * @param $data
     * @return $this
     */
    public function posterPush($data): SaBpDataTopic
    {
        $this->setEventType(self::EVENT_POSTER_PUSH);
        $this->setMsgBody($data);
        return $this;
    }

    public function sendAssistantSms($data)
    {
        $this->setEventType(self::EVENT_ASSISTANT_SMS);
        $this->setMsgBody($data);
        return $this;
    }

	/**
	 * 用户注册渠道属性上报
	 * @param $data
	 * @return $this
	 */
	public function updateUserProfile($data): SaBpDataTopic
	{
		$this->setEventType(self::EVENT_UPDATE_USER_PROFILE);
		$this->setMsgBody($data);
		return $this;
	}

    /**
     * 设置推送event_type和需要发送的数据
     * @param $eventType
     * @param $data
     * @return $this
     */
    public function setPushEventData($eventType, $data)
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }
}
