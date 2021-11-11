<?php


namespace App\Services;


use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Models\TrackAndroidModel;
use App\Models\TrackIosModel;
use App\Models\TrackUserModel;
use App\Models\TrackWebModel;

class RealAd
{
    const TRACK_EVENT_INIT = 0; // 用户信息初始化，不用回调
    const TRACK_EVENT_ACTIVE = 1;
    const TRACK_EVENT_REGISTER = 2;
    const TRACK_EVENT_FORM_COMPLETE = 3;
    const TRACK_EVENT_PAY = 4;

    const INVALID_IDFA = '00000000-0000-0000-0000-000000000000';

    const PLAT_ID_UNKNOWN = 0;
    const PLAT_ID_ANDROID = 1;
    const PLAT_ID_IOS = 2;
    const PLAT_ID_WEB = 3;


    public static function adActive($msgBody)
    {
        switch ($msgBody['ad_channel']) {
            case RealAdCallback::CHANNEL_HUAWEI:
                $trackIdArr          = json_decode($msgBody['track_id'], true);
                $msgBody['ext']      = json_encode([
                    'channel'  => $trackIdArr['channel'] ?? '',
                    'taskid'   => $trackIdArr['taskid'] ?? '',
                    'callback' => $trackIdArr['callback'] ?? '',
                ]);
                $msgBody['ad_id']    = $trackIdArr['channel'] ?? '';
                $msgBody['callback'] = $trackIdArr['callback'] ?? '';
                unset($msgBody['track_id']);
        }

        return $msgBody;
    }

    public static function checkoutPlatform($platform)
    {
        if (empty($platform)){
            return false;
        }

        return in_array($platform, [self::PLAT_ID_ANDROID, self::PLAT_ID_IOS, self::PLAT_ID_WEB]);
    }

    public static function trackEvent($eventType, $trackParams)
    {
        $completeParams = self::completeHashData($trackParams);
        switch ($eventType) {
            case self::TRACK_EVENT_INIT;
                $res = self::handleInit($completeParams);
                break;
            case self::TRACK_EVENT_ACTIVE;
                $res = self::handleActive($completeParams);
                break;
            case self::TRACK_EVENT_REGISTER;
                //APP端注册事件
                $res = self::handleRegister($completeParams);
                break;
            case self::TRACK_EVENT_FORM_COMPLETE;
                //landing页注册事件
                $res = self::handleFormComplete($completeParams);
                break;
            case self::TRACK_EVENT_PAY;
                $res = self::handlePay($completeParams);
                break;
        }
        return [
            'complete'   => $res ?? false,
            'ad_channel' => $completeParams['ad_channel'] ?? 0,
            'ad_id'      => $completeParams['ad_id'] ?? 0,
        ];
    }

    public static function completeHashData($data)
    {
        if (!empty($data['idfa']) && empty($data['idfa_hash'])) {
            $data['idfa_hash'] = md5(strtoupper($data['idfa']));
        }
        if (!empty($data['imei']) && empty($data['imei_hash'])) {
            $data['imei_hash'] = md5(strtolower($data['imei']));
        }
        if (!empty($data['android_id']) && empty($data['android_id_hash'])) {
            $data['android_id_hash'] = md5(strtolower($data['android_id']));
        }

        return $data;
    }

    public static function hasStateFlag($trackState, $eventType)
    {
        return $trackState & self::trackStateFlag($eventType);
    }

    public static function trackStateFlag($eventType)
    {
        return 1 << ($eventType - 1);
    }

    public static function handleInit($completeParams)
    {
        SimpleLogger::info("[trackEvent_Init] start:", $completeParams);
        $exist = self::match(self::TRACK_EVENT_INIT, $completeParams);
        if (!empty($exist)) {
            SimpleLogger::debug("[trackEvent_Init] event has been updated", $completeParams);
            return false;
        }
        self::addInfo(self::TRACK_EVENT_INIT, $completeParams);
        return true;
    }

    public static function handleActive($completeParams)
    {
        SimpleLogger::info("[trackEvent_Active] start:", $completeParams);
        $exist = self::match(self::TRACK_EVENT_ACTIVE, $completeParams);
        if (empty($exist)) {
            self::addInfo(self::TRACK_EVENT_INIT, $completeParams);
            $exist = self::match(self::TRACK_EVENT_ACTIVE, $completeParams);
        }

        if (!empty($completeParams['ad_channel']) && $exist['ad_channel'] != $completeParams['ad_channel'] && $completeParams['platform'] != self::PLAT_ID_IOS) {
            SimpleLogger::info("[trackEvent_Active] ad_channel is different", []);
            return false;
        }

        if (self::hasStateFlag($exist['track_state'], self::TRACK_EVENT_ACTIVE)) {
            SimpleLogger::info("[trackEvent_Active] event has been updated", []);
            return false;
        }

        $success = RealAdCallback::trackCallback(self::TRACK_EVENT_ACTIVE, $exist);
        if ($success) {
            $update = [
                'track_state' => $exist['track_state'] | self::trackStateFlag(self::TRACK_EVENT_ACTIVE),
                'active_time' => time()
            ];
            TrackUserModel::updateRecord($exist['tu_id'], $update);
            return true;
        }
        return false;
    }

    public static function handleRegister($completeParams)
    {
        SimpleLogger::info("[trackEvent_Register] start:", $completeParams);
        $time      = time();
        $trackUser = TrackUserModel::getRecord(['user_id' => $completeParams['user_id']]);
        if (!empty($trackUser)) {
            SimpleLogger::info("[trackEvent_Register] user has registered", []);
            return false;
        }

        $exist = self::match(self::TRACK_EVENT_REGISTER, $completeParams);
        if (empty($exist)) {
            SimpleLogger::info("[trackEvent_Register] record not found", []);
            return false;
        }

        if (!empty($completeParams['ad_channel']) && $exist['ad_channel'] != $completeParams['ad_channel'] && $completeParams['platform'] != self::PLAT_ID_IOS) {
            SimpleLogger::info("[trackEvent_Active] ad_channel is different", []);
            return false;
        }

        $success = false;
        if (!(self::hasStateFlag($exist['track_state'], self::TRACK_EVENT_REGISTER))) {
            $success = RealAdCallback::trackCallback(self::TRACK_EVENT_REGISTER, $exist);
        }

        if ($success && empty($exist['user_id'])) {
            //设备上首次发生注册行为
            $update = [
                'track_state'   => $exist['track_state'] | self::trackStateFlag(self::TRACK_EVENT_REGISTER),
                'user_id'       => $completeParams['user_id'],
                'register_time' => $time,
            ];
            TrackUserModel::updateRecord($exist['tu_id'], $update);
            return true;
        } elseif ($success && !empty($exist['user_id'])) {
            //设备上再次发生注册行为
            $insert = [
                'platform_type'  => $exist['platform_type'],
                'platform_id'    => $exist['platform_id'],
                'ad_channel'     => $exist['ad_channel'],
                'channel_id'     => $exist['channel_id'],
                'again_register' => 1,
                'user_id'        => $completeParams['user_id'],
                'track_state'    => self::trackStateFlag(self::TRACK_EVENT_REGISTER),
                'create_time'    => $time,
                'register_time'  => $time,
            ];
            TrackUserModel::insertRecord($insert);
            return true;
        }
        return false;
    }

    public static function handleFormComplete($completeParams)
    {
        SimpleLogger::info("[trackEvent_FormComplete] start:", $completeParams);
        $exist = self::match(self::TRACK_EVENT_FORM_COMPLETE, $completeParams);
        if (empty($exist)) {
            self::addInfo(self::TRACK_EVENT_FORM_COMPLETE, $completeParams);
            $exist = self::match(self::TRACK_EVENT_FORM_COMPLETE, $completeParams);
        } else {
            $exist['mobile'] = $completeParams['mobile'] ?? '';
        }

        if (!empty($completeParams['ad_channel']) && $exist['ad_channel'] != $completeParams['ad_channel']) {
            SimpleLogger::info("[trackEvent_FormComplete] ad_channel is different", []);
            return false;
        }

        if (!empty($exist) && self::hasStateFlag($exist['track_state'], self::TRACK_EVENT_FORM_COMPLETE)) {
            SimpleLogger::info("[trackEvent_FormComplete] event has been updated", []);
            return false;
        }

        $success = RealAdCallback::trackCallback(self::TRACK_EVENT_FORM_COMPLETE, $exist);
        if ($success) {
            $update = [
                'track_state'   => $exist['track_state'] | self::trackStateFlag(self::TRACK_EVENT_FORM_COMPLETE),
                'register_time' => time(),
            ];
            TrackUserModel::updateRecord($exist['tu_id'], $update);
            return true;
        }
        return false;
    }

    public static function handlePay($completeParams)
    {
        SimpleLogger::info("[trackEvent_Pay] start:", $completeParams);
        $exist = self::match(self::TRACK_EVENT_PAY, $completeParams);
        if (empty($exist)) {
            SimpleLogger::info("[trackEvent_Pay] record not found", []);
            return false;
        } else {
            $exist['mobile'] = $completeParams['mobile'] ?? '';
        }

        if (self::hasStateFlag($exist['track_state'], self::TRACK_EVENT_PAY)) {
            SimpleLogger::info("[trackEvent_Pay] event has been updated", []);
            return false;
        }

        $success = RealAdCallback::trackCallback(self::TRACK_EVENT_PAY, $exist);
        if ($success) {
            $update = [
                'track_state'    => $exist['track_state'] | self::trackStateFlag(self::TRACK_EVENT_PAY),
                'trial_pay_time' => time()
            ];
            TrackUserModel::updateRecord($exist['tu_id'], $update);
            return true;
        }
        return false;
    }

    /****************************************************以下为平台分支处理***************************************************************/

    public static function match($eventType, $completeParams)
    {
        if ($eventType < self::TRACK_EVENT_FORM_COMPLETE) {
            if ($completeParams['platform'] == self::PLAT_ID_IOS) {
//                $exist = self::matchIos($completeParams);
            } elseif ($completeParams['platform'] == self::PLAT_ID_ANDROID) {
                $exist = self::matchAndroid($completeParams);
            }
        } elseif ($eventType == self::TRACK_EVENT_FORM_COMPLETE) {
//            $exist = self::matchWeb($completeParams);
        } elseif ($eventType == self::TRACK_EVENT_PAY) {
            $exist = self::matchUser($completeParams);
        }
        return $exist ?? [];
    }

    public static function addInfo($eventType, $completeParams)
    {
        if ($eventType < self::TRACK_EVENT_REGISTER) {
            if ($completeParams['platform'] == self::PLAT_ID_IOS) {
//                $res = self::addIosInfo($eventType, $completeParams);
            } elseif ($completeParams['platform'] == self::PLAT_ID_ANDROID) {
                $res = self::addAndroidInfo($eventType, $completeParams);
            }
        } elseif ($eventType == self::TRACK_EVENT_FORM_COMPLETE) {
//            $res = self::addWebInfo($eventType, $completeParams);
        }
        return $res ?? false;
    }


    /****************************************************以下为安卓相关回传***************************************************************/

    public static function matchAndroid($params)
    {
        $matchKeys = [
            'imei', 'imei_hash', 'android_id', 'android_id_hash', 'oaid'
        ];

        $or = [];
        foreach ($matchKeys as $k) {
            if (!empty($params[$k])) {
                $or[$k] = $params[$k];
            }
        }

        if (empty($or)) {
            return NULL;
        }

        return TrackAndroidModel::matchAndroidInfo(['OR' => $or]);
    }

    public static function addAndroidInfo($eventType, $info)
    {
        $time = time();
        $db   = MysqlDB::getDB(MysqlDB::CONFIG_AD);
        $db->beginTransaction();
        try {
            $androidData = [
                'ad_channel'      => $info['ad_channel'] ?? 0,
                'imei'            => $info['imei'] ?? '',
                'imei_hash'       => $info['imei_hash'] ?? '',
                'android_id'      => $info['android_id'] ?? '',
                'android_id_hash' => $info['android_id_hash'] ?? '',
                'oaid'            => $info['oaid'] ?? '',
                'callback'        => $info['callback'] ?? '',
                'create_time'     => $time,
                'init_time'       => $time,
            ];

            if (!empty($info['ext'])) {
                $androidData['ext'] = $info['ext'];
            }

            $id = TrackAndroidModel::insertRecord($androidData);

            $userDate = [
                'platform_type' => self::PLAT_ID_ANDROID,
                'platform_id'   => $id,
                'ad_channel'    => $androidData['ad_channel'],
                'channel_id'    => $info['channel_id'] ?? 0,
                'ad_id'         => $info['ad_id'] ?? '',
                'track_state'   => ($eventType > 0) ? self::trackStateFlag($eventType) : 0,
                'create_time'   => $time,
            ];

            TrackUserModel::insertRecord($userDate);
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
        return true;
    }

    /****************************************************以下为IOS相关回传****************************************************************/

//    public static function matchIos($params)
//    {
//        if ($params['idfa'] != self::INVALID_IDFA) {
//            $caseOne = [
//                'idfa'      => $params['idfa'],
//                'idfa_hash' => $params['idfa_hash'],
//            ];
//        }
//
//        if ($params['idfa'] == self::INVALID_IDFA && !empty($params['ios_uuid'])) {
//            $caseTwo['ios_uuid'] = $params['ios_uuid'];
//        }
//
//        if ($params['idfa'] == self::INVALID_IDFA && !empty($params['ip']) && !empty($params['device_model']) && !empty($params['ua'])) {
//            $caseThree = [
//                'ip'           => $params['ip'],
//                'device_model' => $params['device_model'],
//                'ua'           => $params['ua'],
//            ];
//        }
//
//        if (empty($caseOne) && empty($caseTwo) && empty($caseThree)) {
//            return NULL;
//        }
//
//        if (!empty($caseOne)) {
//            $res = TrackIosModel::matchIosInfo(['OR' => $caseOne]);
//        } elseif (!empty($caseTwo) && !empty($caseThree)) {
//            $res = TrackIosModel::matchIosInfo(['AND' => $caseTwo]);
//            if (!empty($res)) {
//                return $res;
//            }
//            $res = TrackIosModel::matchIosInfo(['AND' => $caseThree]);
//        } elseif (!empty($caseTwo)) {
//            $res = TrackIosModel::matchIosInfo(['AND' => $caseTwo]);
//        } elseif (!empty($caseThree)) {
//            $res = TrackIosModel::matchIosInfo(['AND' => $caseThree]);
//        }
//
//        return $res ?? [];
//    }

    /**
     * @param $eventType
     * @param $info
     * @return bool
     * ios平台添加设备信息仅允许在初始化和激活阶段
     */
//    public static function addIosInfo($eventType, $info)
//    {
//        $time = time();
//        $db = MysqlDB::getDB(MysqlDB::CONFIG_AD);
//        $db->beginTransaction();
//        try {
//            $iosData = [
//                'ad_channel'   => $info['ad_channel'] ?? 0,
//                'idfa'         => $info['idfa'] ?? '',
//                'idfa_hash'    => $info['idfa_hash'] ?? '',
//                'ios_uuid'     => $info['ios_uuid'] ?? '',
//                'ip'           => $info['ip'] ?? 0,
//                'device_model' => $info['device_model'] ?? '',
//                'ua'           => $info['ua'] ?? '',
//                'callback'     => $info['callback'] ?? '',
//                'create_time'  => $time,
//                'init_time'  => $time,
//            ];
//
//            $id = TrackIosModel::insertRecord($iosData);
//
//            $userDate = [
//                'platform_type' => self::PLAT_ID_IOS,
//                'platform_id'   => $id,
//                'ad_channel'    => $iosData['ad_channel'],
//                'channel_id'    => $info['channel_id'] ?? 0,
//                'ad_id'         => $info['ad_id'] ?? '',
//                'track_state'   => ($eventType > 0) ? self::trackStateFlag($eventType) : 0,
//                'create_time'   => $time,
//            ];
//            TrackUserModel::insertRecord($userDate);
//            $db->commit();
//        } catch (\Exception $e) {
//            $db->rollBack();
//            return false;
//        }
//        return true;
//    }

    /****************************************************以下为WEB相关回传****************************************************************/

//    public static function matchWeb($params)
//    {
//        return TrackWebModel::matchWebInfo($params['user_id']) ?? [];
//    }

//    public static function addWebInfo($eventType, $info)
//    {
//        $time = time();
//        $db = MysqlDB::getDB(MysqlDB::CONFIG_AD);
//        $db->beginTransaction();
//        try {
//            $webData = [
//                'ad_channel'  => $info['ad_channel'] ?? 0,
//                'channel_id'  => $info['channel_id'] ?? 0,
//                'callback'    => $info['callback'] ?? '',
//                'ref'         => $info['ref'] ?? '',
//                'user_id'     => $info['user_id'] ?? 0,
//                'create_time' => $time,
//            ];
//            $id = TrackWebModel::insertRecord($webData);
//
//            $userDate = [
//                'platform_type' => self::PLAT_ID_WEB,
//                'platform_id'   => $id,
//                'ad_channel'    => $webData['ad_channel'],
//                'channel_id'    => $webData['channel_id'],
//                'ad_id'         => $info['ad_id'] ?? '',
//                'user_id'       => $info['user_id'] ?? 0,
//                'track_state'   => 0,
//                'create_time'   => $time,
//                'register_time' => $time,
//            ];
//            TrackUserModel::insertRecord($userDate);
//            $db->commit();
//        } catch (\Exception $e) {
//            $db->rollBack();
//            return false;
//        }
//        return true;
//    }


    /****************************************************以下为TrackUser相关回传****************************************************************/
    public static function matchUser($params)
    {
        $trackUser = TrackUserModel::getRecord(['user_id' => $params['user_id']]);
        if (empty($trackUser)) {
            return [];
        }
        $trackUser['tu_id'] = $trackUser['id'];
        unset($trackUser['id']);
        switch ($trackUser['platform_type']) {
            case self::PLAT_ID_ANDROID:
                $trackBase = TrackAndroidModel::getRecord(['id' => $trackUser['platform_id']]);
                break;
//            case self::PLAT_ID_IOS:
//                $trackBase = TrackIosModel::getRecord(['id'=>$trackUser['platform_id']]);
//                break;
//            case self::PLAT_ID_WEB:
//                $trackBase = TrackWebModel::getRecord(['id'=>$trackUser['platform_id']]);
//                break;
            default:
                $trackBase = [];
        }
        return array_merge($trackUser, $trackBase);
    }
}