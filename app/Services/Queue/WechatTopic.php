<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021/3/24
 * Time: 5:45 PM
 */

namespace App\Services\Queue;

class WechatTopic extends BaseTopic
{
    const TOPIC_NAME = "operation_wechat";

    const EVENT_UPDATE_USER_TAG = 'update_user_tag';
    const EVENT_GET_MINI_APP_QR = 'get_mini_app_qr';                      // 请求微信生成小程序码
    const EVENT_CREATE_MINI_APP_ID = 'create_mini_app_identification';    // 生成小程序码标识
    const EVENT_CREATE_WAIT_USE_QR_ID = 'create_wait_user_qr_id';         // 生成待使用标识

    /**
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 更新用户标签
     * @param $data
     * @return $this
     */
    public function updateUserTag($data)
    {
        $this->setEventType(self::EVENT_UPDATE_USER_TAG);
        $this->setMsgBody($data);
        return $this;
    }

    /**
     * 发送待生成小程序码id到队列
     * @param $data
     * @return $this
     */
    public function waitCreateMiniAppQrId($data)
    {
        $this->setEventType(self::EVENT_GET_MINI_APP_QR);
        $this->setMsgBody($data);
        return $this;
    }

    /**
     * 启动生成小程序码id的任务
     * @param $data
     * @return $this
     */
    public function startCreateMiniAppId($data)
    {
        $this->setEventType(self::EVENT_CREATE_MINI_APP_ID);
        $this->setMsgBody($data);
        return $this;
    }

    /**
     * 启动生成待使用二维码标识
     * @param $data
     * @return $this
     */
    public function startCreateWaitUseQrId($data)
    {
        $this->setEventType(self::EVENT_CREATE_WAIT_USE_QR_ID);
        $this->setMsgBody($data);
        return $this;
    }
}