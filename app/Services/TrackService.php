<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/10/18
 * Time: 5:42 PM
 */

namespace App\Services;


use App\Libs\MysqlDB;

class TrackService
{
    const TRACK_EVENT_ACTIVE = 1;
    const TRACK_EVENT_REGISTER = 2;

    const PLAT_ANDROID = 'android';
    const PLAT_IOS = 'ios';

    public static function addAdInfo($adInfo)
    {
        return false;
    }

    public static function addInfo($info, $eventType = NULL)
    {
        return false;
    }

    public static function trackEvent($platform, $eventType, $params)
    {
        if ($platform == self::PLAT_IOS) {
            $trackData = self::matchIos($params);
        } elseif ($platform == self::PLAT_ANDROID) {
            $trackData = self::matchAndroid($params);
        }

        // 未匹配上，插入新用户ad信息
        if (empty($trackData)) {
            $success = self::addInfo([], $eventType);

            return [
                'complete' => $success,
                'ad_channel' => 0,
                'ad_id' => 0,
            ];
        }

        // 回调渠道商
        $success = self::trackCallback(self::TRACK_EVENT_ACTIVE, $trackData);
        if ($success) {
            // update callback state
        }

        return [
            'complete' => $success,
            'ad_channel' => $trackData['ad_channel'],
            'ad_id' => $trackData['ad_id'],
        ];
    }

    public static function getTrackParams($platform, $params)
    {
        $trackParams = [];
        if ($platform == self::PLAT_IOS) {
            if (!empty($params['idfa'])) {
                $trackParams['idfa'] = $params['idfa'];
            }

        }
        return $trackParams;
    }


    public static function matchAndroid($params)
    {
        return [];
    }

    public static function matchIos($params)
    {
        $idfa = $params['idfa'];
        if (empty($idfa)) {
            return NULL;
        }

        $db = MysqlDB::getDB();
        $match = $db->get('track', '*', [
            'idfa' => $idfa
        ]);
        return $match;
    }

    public static function trackCallback($eventType, $match)
    {
        return false;
    }
}