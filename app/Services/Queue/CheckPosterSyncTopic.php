<?php


namespace App\Services\Queue;


class CheckPosterSyncTopic extends BaseTopic {

    const TOPIC_NAME = "check_poster_sync";

    const CHECK_POSTER = 'check_poster'; //审核海报


    /**
     * StudentSyncTopic constructor.
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }


    public function checkPoster($data)
    {
        $this->setEventType(self::CHECK_POSTER);
        $this->setMsgBody($data);
        return $this;
    }
}