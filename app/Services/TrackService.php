<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/10/18
 * Time: 5:42 PM
 */

namespace App\Services;


use App\Libs\MysqlDB;
use App\Models\TrackModel;

class TrackService
{
    const TRACK_EVENT_ACTIVE = 1;
    const TRACK_EVENT_REGISTER = 2;

    const PLAT_UNKNOWN = 'unknown_plat';
    const PLAT_ANDROID = 'android';
    const PLAT_IOS = 'ios';

    const PLAT_ID_UNKNOWN = 0;
    const PLAT_ID_ANDROID = 1;
    const PLAT_ID_IOS = 2;

    const PARAMS_IOS = ['idfa', 'idfa_hash'];
    const PARAMS_ANDROID = ['imei', 'imei_hash'];

    const CHANNEL_OTHER = 0;
    const CHANNEL_OCEAN = 1;
    const CHANNEL_GDT = 2;
    const CHANNEL_WX = 3;

    public static function addAdInfo($channel, $info)
    {
        return false;
    }

    public static function addInfo($info, $eventType = NULL)
    {
        $trackData = [];
        $trackData['platform'] = $info['platform'];
        $trackData['ad_channel'] = self::CHANNEL_OTHER;
        $trackData['ad_id'] = 0;
        $trackData['idfa'] = $info['idfa'] ?? '';
        $trackData['idfa_hash'] = $info['idfa_hash'] ?? '';
        $trackData['imei'] = $info['imei'] ?? '';
        $trackData['imei_hash'] = $info['imei_hash'] ?? '';
        $trackData['android_id'] = $info['android_id'] ?? '';
        $trackData['android_id_hash'] = $info['android_id_hash'] ?? '';
        $trackData['mac_hash'] = $info['mac_hash'] ?? '';
        $trackData['track_state'] = $eventType ?? 0;
        $trackData['callback'] = $info['callback'] ?? '';
        $trackData['create_time'] = $info['create_time'] ?? time();
        $trackData['user_id'] = $info['user_id'] ?? NULL;

        $id = TrackModel::insertRecord($trackData, false);
        return $id > 0;
    }

    public static function trackEvent($platform, $eventType, $trackParams)
    {
        if ($platform == self::PLAT_IOS) {
            $trackParamKeys = self::PARAMS_IOS;
        } elseif ($platform == self::PLAT_ANDROID) {
            $trackParamKeys = self::PARAMS_ANDROID;
        } else {
            return NULL;
        }

        $trackData = self::match($trackParamKeys, $trackParams);

        // 未匹配上，插入新用户ad信息
        if (empty($trackData)) {
            $success = self::addInfo($trackParams, $eventType);

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

    public static function match($keys, $trackParams)
    {
        $or = [];
        foreach ($keys as $key) {
            if (!empty($trackParams[$key])) {
                $or[$key] = $trackParams[$key];
            }
        }

        $db = MysqlDB::getDB();
        $match = $db->get('track', '*', [
            'OR' => $or
        ]);
        return $match;
    }

    public static function trackCallback($eventType, $match)
    {
        return false;
    }
}