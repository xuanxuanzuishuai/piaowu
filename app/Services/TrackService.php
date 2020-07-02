<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/10/18
 * Time: 5:42 PM
 */

namespace App\Services;


use App\Libs\DictConstants;
use App\Libs\HttpHelper;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Models\TrackModel;
use App\Models\UserWeixinModel;

class TrackService
{
    // 追踪事件
    const TRACK_EVENT_INIT = 0; // 用户信息初始化，不用回调
    const TRACK_EVENT_ACTIVE = 1;
    const TRACK_EVENT_REGISTER = 2;
    const TRACK_EVENT_FORM_COMPLETE = 3;

    const PLAT_UNKNOWN = 'unknown_plat';
    const PLAT_ANDROID = 'android';
    const PLAT_IOS = 'ios';

    const PLAT_ID_UNKNOWN = 0;
    const PLAT_ID_ANDROID = 1;
    const PLAT_ID_IOS = 2;

    const PARAMS_IOS = ['idfa', 'idfa_hash'];
    const PARAMS_ANDROID = ['imei', 'imei_hash', 'android_id', 'android_id_hash'];

    // 广告渠道，相同厂商的不同转化流程使用不同的渠道
    const CHANNEL_OTHER = 0;
    const CHANNEL_OCEAN = 1; // 头条，app转化追踪
    const CHANNEL_GDT = 2;
    const CHANNEL_WX = 3;
    const CHANNEL_OCEAN_LEADS = 4; // 头条，线索转化追踪
    const CHANNEL_IOS_IDFA = 5; // iOS 激活推广 idfa

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
        $trackData['track_state'] = ($eventType > 0) ? 1 << ($eventType-1) : 0;
        $trackData['callback'] = $info['callback'] ?? '';
        $trackData['create_time'] = $info['create_time'] ?? time();
        $trackData['user_id'] = $info['user_id'] ?? NULL;

        $id = TrackModel::insertRecord($trackData, false);
        return $id > 0;
    }

    public static function trackEvent($eventType, $trackParams, $userId = NULL)
    {
        $completeParams = self::completeHashData($trackParams);

        // 查找是否
        $trackData = self::match($completeParams);

        // 未匹配上，插入新用户ad信息
        if (empty($trackData)) {
            $completeParams['user_id'] = $userId;
            $success = self::addInfo($completeParams, $eventType);

            // 如果新用户触发的不是init事件，还需要走对渠道商应事件回调
            if ($success && $eventType != self::TRACK_EVENT_INIT) {
                $success = self::trackCallback($eventType, $completeParams);
            }

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
            SimpleLogger::debug("[trackEvent] event has been updated", []);
            return [
                'complete' => true,
                'ad_channel' => $trackData['ad_channel'],
                'ad_id' => $trackData['ad_id'],
            ];
        }

        // 过滤不同广告渠道商的事件，只接受第一个广告渠道的后续事件
        if (!empty($trackParams['ad_channel']) && $trackParams['ad_channel'] != $trackData['ad_channel']) {
            SimpleLogger::debug("[trackEvent] different ad_channel", [
                'current_event_ad_channel' => $trackParams['ad_channel'],
                'user_track_ad_channel' => $trackData['ad_channel']
            ]);
            return [
                'complete' => false,
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
        SimpleLogger::debug("[trackEvent] callback", ['ad_channel' => $trackData['ad_channel']]);
        switch ($trackData['ad_channel']) {
            case self::CHANNEL_OTHER:
                return true;
            case self::CHANNEL_OCEAN:
                return self::trackCallbackOceanEngine($eventType, $trackData);
            case self::CHANNEL_GDT:
                return self::trackCallbackGDT($eventType, $trackData);
            case self::CHANNEL_WX:
                return self::trackCallbackWX($eventType, $trackData);
            case self::CHANNEL_OCEAN_LEADS:
                return self::trackCallbackOceanEngineLeads($eventType, $trackData);
            case self::CHANNEL_IOS_IDFA:
                return self::trackCallBackIdfa($eventType, $trackData);
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

        $response = HttpHelper::requestJson($api, $data, 'GET');
        $success = (!empty($response) && $response['ret'] == 0);

        return $success;
    }

    public static function trackCallbackOceanEngineLeads($eventType, $trackData)
    {
        $api = 'http://ad.toutiao.com/track/activate/';
        switch ($eventType) {
            case self::TRACK_EVENT_FORM_COMPLETE: // 初始化操作不用回调接口
                $type = 3;
                break;
            default:
                return false;
        }

        $data = [
            'event_type' => $type,
            'link' => $trackData['callback'],
            'conv_time' => time(),
            'source' => $trackData['ad_id']
        ];

        $response = HttpHelper::requestJson($api, $data, 'GET');
        $success = (!empty($response) && $response['ret'] == 0);

        return $success;
    }

    public static function trackCallbackGDT($eventType, $trackData)
    {
        $api = 'https://api.e.qq.com/v1.1/user_actions/add';
        switch ($eventType) {
            case self::TRACK_EVENT_FORM_COMPLETE: // 初始化操作不用回调接口
                $type = 'RESERVATION';
                break;
            default:
                return false;
        }

        $commonParameters = array (
            'access_token' => $_ENV['GDT_ACCESS_TOKEN'],
            'timestamp' => time(),
            'nonce' => md5(uniqid('', true))
        );

        $parameters = [
            'account_id' => $_ENV['GDT_ACCOUNT_ID'],
            'user_action_set_id' => $_ENV['GDT_USER_ACTION_SET_ID'],
            'actions' => [
                [
                    'action_time' => time(),
                    'user_id' => [
                        'hash_phone' => md5($trackData['mobile'])
                    ],
                    'action_type' => $type,
                    'trace' => [
                        'click_id' => $trackData['qz_gdt']
                    ],
                ]
            ]
        ];
        $api = $api . '?' . http_build_query($commonParameters);

        $response = HttpHelper::requestJson($api, $parameters, 'POST');
        $success = (!empty($response) && $response['ret'] == 0);

        return $success;
    }

    public static function trackCallbackWX($eventType, $trackData)
    {
        switch ($eventType) {
            case self::TRACK_EVENT_FORM_COMPLETE: // 初始化操作不用回调接口
                $type = 'RESERVATION';
                break;
            default:
                return false;
        }

        //注册成功后，反馈给微信广告平台
        $userActionSetId = DictConstants::get(DictConstants::LANDING_CONFIG, 'user_action_set_id');
        $accessToken = WeChatService::getAccessToken(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, UserWeixinModel::USER_TYPE_STUDENT);
        $success = WeChatService::feedback($accessToken, [
            'actions' => [
                [
                    'user_action_set_id' => $userActionSetId,
                    'action_time'        => time(),
                    'action_type'        => $type,
                    'url'                => 'http://www.xiaoyezi.com/index.html',
                    'trace'              => ['click_id' => $trackData['gdt_vid']]
                ]
            ]
        ]);

        return $success;
    }

    public static function trackCallBackIdfa($eventType, $trackData)
    {
        if ($eventType != self::TRACK_EVENT_ACTIVE) {
            return 0;
        }

        $response = HttpHelper::requestJson($trackData['callback']);
        $success = (!empty($response) && $response['errno'] == 0);

        return $success;
    }

    public static function getAdChannel($userId)
    {
        $adChannel = TrackModel::getRecord(['user_id' => $userId], ['ad_channel', 'ad_id'], false);
        return [
            'ad_channel' => (int)$adChannel['ad_channel'],
            'ad_id' => (int)$adChannel['ad_id'],
        ];
    }
}