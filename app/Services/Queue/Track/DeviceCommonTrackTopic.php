<?php
/**
 * 设备信息上报推送到防羊毛系统
 */

namespace App\Services\Queue\Track;

use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Services\Queue\BaseTopic;
use Exception;

class DeviceCommonTrackTopic extends BaseTopic
{
    const TOPIC_NAME = "common_track";

    const EVENT_TYPE_LOGIN        = 'login';   // login or register all this event
    const EVENT_TYPE_CREATE_ORDER = 'create_order';

    // 来源
    const FROM_TYPE_OTHER    = 0;   // 其他
    const FROM_TYPE_WX       = 1;   // 微信
    const FROM_TYPE_H5       = 2;   // h5
    const FROM_TYPE_APP      = 3;   // APP
    const FROM_TYPE_MINI_APP = 4;   // 小程序

    // 设备类型
    const DEVICE_TYPE_UNKNOWN = 0;  // 未知
    const DEVICE_TYPE_ANDROID = 1;  // 安卓
    const DEVICE_TYPE_ISO     = 2;  // 苹果

    // 订单类型
    const ORDERY_TYPE_DEFAULT = 0;  // 默认
    const ORDER_TYPE_TRAIL    = 1;  // 体验卡课
    const ORDER_TYPE_NORMAL   = 2;  // 正式课

    /**
     * constructor.
     * @param null $publishTime
     * @throws Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 注册事件
     * @param array $params
     * @return $this
     */
    public function pushLogin(array $params)
    {
        $msgBody = array(
            'from'             => $params['from'] ?? (string)self::FROM_TYPE_OTHER,
            'device_type'      => $params['device_type'] ?? (string)self::DEVICE_TYPE_UNKNOWN,
            'channel_id'       => (int)$params['channel_id'] ?? 0,
            'event_time'       => $params['event_time'] ?? time(),
            'imei'             => $params['imei'] ?? '',
            'android_id'       => $params['android_id'] ?? '',
            'oaid'             => $params['oaid'] ?? '',
            'idfa'             => $params['idfa'] ?? '',
            'ios_uuid'         => $params['ios_uuid'] ?? '',
            'open_id'          => $params['open_id'] ?? '',
            'user_agent'       => $params['user_agent'] ?? (!empty($_SERVER['HTTP_USER_AGENT']) ? trim($_SERVER['HTTP_USER_AGENT']) : ''),
            'anonymous_id'     => $params['anonymous_id'] ?? '',
            'device_model'     => $params['device_model'] ?? '',
            'device_user_name' => $params['device_user_name'] ?? '',
            'os'               => $params['os'] ?? '',
            'manu'             => $params['manu'] ?? '',
            'app_version'      => $params['app_version'] ?? '',
            'ip'               => $params['ip'] ?? Util::getClientIp(),
            'uuid'             => $params['uuid'] ?? '',
            'new_user'         => $params['new_user'] ?? '',
            'position'         => $params['position'] ?? '',
        );
        SimpleLogger::info("topic:common_track:pushData", ['params' => $params, 'msg_body' => $msgBody]);
        $this->setEventType(self::EVENT_TYPE_LOGIN);
        $this->setMsgBody($msgBody);
        return $this;
    }

    /**
     * 创建订单事件
     * @param array $params
     * @return $this
     */
    public function pushCreateOrder(array $params)
    {
        $msgBody = array(
            'from'         => $params['from'] ?? (string)self::FROM_TYPE_OTHER,
            'device_type'  => $params['device_type'] ?? (string)self::DEVICE_TYPE_UNKNOWN,
            'channel_id'   => (int)$params['channel_id'] ?? 0,
            'event_time'   => $params['event_time'] ?? time(),
            'imei'         => $params['imei'] ?? '',
            'android_id'   => $params['android_id'] ?? '',
            'oaid'         => $params['oaid'] ?? '',
            'idfa'         => $params['idfa'] ?? '',
            'ios_uuid'     => $params['ios_uuid'] ?? '',
            'open_id'      => $params['open_id'] ?? '',
            'user_agent'   => $params['user_agent'] ?? (!empty($_SERVER['HTTP_USER_AGENT']) ? trim($_SERVER['HTTP_USER_AGENT']) : ''),
            'anonymous_id' => $params['anonymous_id'] ?? '',
            'device_model' => $params['device_model'] ?? '',
            'os'           => $params['os'] ?? '',
            'manu'         => $params['manu'] ?? '',
            'app_version'  => $params['app_version'] ?? '',
            'ip'           => $params['ip'] ?? Util::getClientIp(),
            'uuid'         => $params['uuid'] ?? '',
            'order_type'   => $params['order_type'] ?? self::ORDERY_TYPE_DEFAULT,
            'order_id'     => $params['order_id'] ?? '',
            'position'     => $params['position'] ?? '',
        );
        SimpleLogger::info("topic:common_track:pushData", ['params' => $params, 'msg_body' => $msgBody]);
        $this->setEventType(self::EVENT_TYPE_CREATE_ORDER);
        $this->setMsgBody($msgBody);
        return $this;
    }
}