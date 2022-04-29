<?php

namespace App\Services\Queue\Track;

/**
 * 广告投放链路追踪，此topic是ad_track系统的消息队列
 */
class CommonTrackTopic
{
    const TOPIC_NAME = "common_track";
    const COMMON_EVENT_TYPE_LOGIN = "login";//登录|注册
    const COMMON_EVENT_TYPE_APP_ACTIVE = "app_active";//激活
    const COMMON_EVENT_TYPE_CREATE_ORDER = "create_order";//创建订单
    const COMMON_EVENT_TYPE_COMMON_TRACK = "purchase";//付费成功
}