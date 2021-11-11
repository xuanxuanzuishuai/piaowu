<?php


namespace App\Services;


use App\Libs\SimpleLogger;

class RealAdCallback
{
    // 广告渠道，相同厂商的不同转化流程使用不同的渠道
    const CHANNEL_HUAWEI = 8;       // 华为【APP】

    public static function trackCallback($eventType, $trackData)
    {
        SimpleLogger::debug("[trackEvent] callback", ['ad_channel' => $trackData['ad_channel']]);

        switch ($trackData['ad_channel']) {
            case self::CHANNEL_HUAWEI:
                return self::trackCallbackHuaWei($eventType, $trackData);
            default:
                return false;
        }
    }

    public static function trackCallbackHuaWei($eventType, $trackData)
    {
        return true;
    }

}