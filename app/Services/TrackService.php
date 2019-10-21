<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/10/18
 * Time: 5:42 PM
 */

namespace App\Services;


use App\Libs\SimpleLogger;
use App\Models\TrackModel;
use GuzzleHttp\Client;
use Slim\Http\StatusCode;

class TrackService
{
    const TRACK_EVENT_INIT = 0;
    const TRACK_EVENT_ACTIVE = 1;
    const TRACK_EVENT_REGISTER = 2;

    const PLAT_UNKNOWN = 'unknown_plat';
    const PLAT_ANDROID = 'android';
    const PLAT_IOS = 'ios';

    const PLAT_ID_UNKNOWN = 0;
    const PLAT_ID_ANDROID = 1;
    const PLAT_ID_IOS = 2;

    const PARAMS_IOS = ['idfa', 'idfa_hash'];
    const PARAMS_ANDROID = ['imei', 'imei_hash', 'android_id', 'android_id_hash'];

    const CHANNEL_OTHER = 0;
    const CHANNEL_OCEAN = 1;
    const CHANNEL_GDT = 2;
    const CHANNEL_WX = 3;

    public static function addInfo($info, $eventType = NULL)
    {
        $trackData = [];
        $trackData['platform'] = $info['platform'];
        $trackData['ad_channel'] = $info['ad_channel'] ?? self::CHANNEL_OTHER;
        $trackData['ad_id'] = $info['ad_id'] ?? 0;
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

    public static function trackEvent($eventType, $trackParams, $userId = NULL)
    {
        $completeParams = self::completeHashData($trackParams);
        $trackData = self::match($completeParams);

        // 未匹配上，插入新用户ad信息
        if (empty($trackData)) {
            $completeParams['user_id'] = $userId;
            $success = self::addInfo($completeParams, $eventType);

            return [
                'complete' => $success,
                'ad_channel' => 0,
                'ad_id' => 0,
            ];
        }

        SimpleLogger::debug("[trackEvent]", [
            '$trackData' => $trackData,
            '$eventType' => $eventType,
            '$trackParams' => $trackParams,
        ]);

        // 过滤重复事件
        if ((int)$trackData['track_state'] & $eventType) {

            return [
                'complete' => true,
                'ad_channel' => $trackData['ad_channel'],
                'ad_id' => $trackData['ad_id'],
            ];
        }

        // 回调渠道商
        $success = self::trackCallback($eventType, $trackData);
        if ($success) {
            // 更新track_state
            $update = ['track_state' => $trackData['track_state'] | $eventType];

            // 更新user_id
            if (!empty($userId) && empty($trackData['user_id'])) {
                $update['user_id'] = $userId;
            }

            foreach ($completeParams as $k => $v) {
                if (empty($trackData[$k]) && !empty($v)) { $update[$k] = $v; }
            }

            $count = TrackModel::updateRecord($trackData['id'], $update, false);
            $success = ($count > 0);
        }

        return [
            'complete' => $success,
            'ad_channel' => $trackData['ad_channel'],
            'ad_id' => $trackData['ad_id'],
        ];
    }

    public static function getPlatformId($platform)
    {
        if ($platform == self::PLAT_IOS) {
            return self::PLAT_ID_IOS;
        } elseif ($platform == self::PLAT_ANDROID) {
            return self::PLAT_ID_ANDROID;
        } else {
            return self::PLAT_ID_UNKNOWN;
        }
    }

    public static function getPlatformParams($platformId, $params)
    {
        $trackParams = [];
        if ($platformId == self::PLAT_ID_IOS) {
            if (!empty($params['idfa'])) { $trackParams['idfa'] = $params['idfa']; }
        } elseif ($platformId == self::PLAT_ID_ANDROID) {
            if (!empty($params['imei'])) { $trackParams['imei'] = $params['imei']; }
            if (!empty($params['android_id'])) { $trackParams['android_id'] = $params['android_id']; }
        }

        return $trackParams;
    }

    public static function completeHashData($data)
    {
        if (!empty($data['idfa']) && empty($data['idfa_hash'])) {
            $data['idfa_hash'] = md5(strtoupper($data['idfa']));
        }
        if (!empty($data['imei']) && empty($data['imei_hash'])) {
            $data['imei_hash'] = md5(strtolower($data['imei']));
        }
        if (!empty($data['mac']) && empty($data['mac_hash'])) {
            $data['mac_hash'] = md5(strtoupper(str_replace(':', '', $data['mac'])));
        }

        return $data;
    }

    public static function match($params)
    {
        $matchKeys = [
            'user_id', 'mac_hash',
            'idfa', 'idfa_hash',
            'imei', 'imei_hash', 'android_id', 'android_id_hash'
        ];

        $or = [];
        foreach ($matchKeys as $k) {
            if (!empty($params[$k])) { $or[$k] = $params[$k]; }
        }

        if (empty($or)) {
            return NULL;
        }

        $match = TrackModel::getRecord(['OR' => $or], '*', false);
        return $match;
    }

    public static function trackCallback($eventType, $trackData)
    {
        switch ($trackData['ad_channel']) {
            case self::CHANNEL_OTHER:
                return true;
            case self::CHANNEL_OCEAN:
                return self::trackCallbackOceanEngine($eventType, $trackData);
            case self::CHANNEL_GDT:
                return true;
            case self::CHANNEL_WX:
                return true;
            default:
                return false;
        }
    }

    public static function trackCallbackOceanEngine($eventType, $trackData)
    {
        $api = 'http://ad.toutiao.com/track/activate/';

        switch ($eventType) {
            case self::TRACK_EVENT_INIT: // 初始化操作不用回调接口
                return true;
            case self::TRACK_EVENT_ACTIVE:
                $type = 0;
                break;
            case self::TRACK_EVENT_REGISTER:
                $type = 1;
                break;
            default:
                return false;
        }

        $data = [
            'event_type' => $type,
            'callback' => $trackData['callback'],
        ];

        if ($trackData['platform'] == self::PLAT_ID_IOS) {
            $data['os'] = 1;
            $data['idfa'] = $trackData['idfa'];
        } elseif ($trackData['platform'] == self::PLAT_ID_ANDROID) {
            $data['os'] = 0;
            $data['imei'] = $trackData['imei_hash'];
            $data['androidid'] = $trackData['android_id_hash'];
        } else {
            return false;
        }

        $response = self::callback($api, $data, 'GET');
        $success = (!empty($response) && $response['ret'] == 0);

        return $success;
    }

    private static function callback($api,  $data = [], $method = 'GET')
    {
        try {
            $client = new Client(['debug' => false]);

            if ($method == 'GET') {
                $data = ['query' => $data];
            } elseif ($method == 'POST') {
                $data = ['json' => $data];
                $data['headers'] = ['Content-Type' => 'application/json'];
            }
            SimpleLogger::info(__FILE__ . ':' . __LINE__, ['api' => $api, 'data' => $data]);
            $response = $client->request($method, $api, $data);
            $body = $response->getBody()->getContents();
            $status = $response->getStatusCode();
            SimpleLogger::info(__FILE__ . ':' . __LINE__, ['api' => $api, 'body' => $body, 'status' => $status]);


            $res = json_decode($body, true);
            SimpleLogger::info(__FILE__ . ':' . __LINE__, [print_r($res, true)]);

            if (($status != StatusCode::HTTP_OK)) {
                return false;
            }
            return $res;

        } catch (\Exception $e) {
            SimpleLogger::error(__FILE__ . ':' . __LINE__, [print_r($e->getMessage(), true)]);
        }

        return false;
    }
}