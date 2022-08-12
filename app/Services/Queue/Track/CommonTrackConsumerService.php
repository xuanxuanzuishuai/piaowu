<?php

namespace App\Services\Queue\Track;

use App\Libs\SimpleLogger;
use App\Services\MorningReferral\MorningReferralStatisticsService;
use App\Services\StudentServices\CollectionService;
use App\Services\StudentServices\ErpStudentService;

class CommonTrackConsumerService extends CommonTrackTopic
{
    //订单类型
    const ORDER_TYPE_TRAIL  = 1;//体验课订单distribution_info是助教信息
    const ORDER_TYPE_NORMAL = 2;//正式课订单distribution_info是课管信息

    /**
     * 订单支付成功
     * @param $paramsData
     */
    public function purchase($paramsData)
    {
        //获取学生基础信息
        $studentBaseData = ErpStudentService::getStudentByUuid($paramsData['msg_body']['uuid']);
        if (empty($studentBaseData)) {
            SimpleLogger::error("student nonexistent", []);
        }
        //区分订单类型，执行不同执行逻辑
        if ($paramsData['msg_body']['order_type'] == self::ORDER_TYPE_TRAIL) {
            //1.发送分班短信
            CollectionService::sendDivideIntoClassesMessage($paramsData['source_app_id'],
                $paramsData['msg_body']['distribution_info'], $studentBaseData[$paramsData['msg_body']['uuid']]);
        } elseif ($paramsData['msg_body']['order_type'] == self::ORDER_TYPE_NORMAL) {
            //todo
        } else {
            SimpleLogger::error("undefined order type", []);
        }
    }

    /**
     * 登录、注册
     * event_type : CommonTrackTopic::COMMON_EVENT_TYPE_LOGIN
     * @param $params
     * @return bool
     */
    public function login($params)
    {
        SimpleLogger::info("morning login", [$params, 'topic' => CommonTrackTopic::COMMON_EVENT_TYPE_LOGIN]);
        return true;
    }
}