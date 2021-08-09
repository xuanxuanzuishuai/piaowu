<?php
/**
 * Created by PhpStorm.
 * User: theone
 * Date: 2021/1/27
 * Time: 11:07 AM
 */

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RC4;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\ParamMapModel;
use App\Models\QrInfoOpCHModel;
use App\Services\Queue\QueueService;

/**
 * 小程序二维码管理类
 * Class MiniAppQrService
 * @package App\Services
 */
class MiniAppQrService
{
    //代理商小程序转介绍票据前缀
    const AGENT_TICKET_PREFIX = 'ag_';

    const REDIS_CREATE_MINI_APP_ID_LOCK   = 'create_mini_app_id_lock';    // 生成小程序码标识锁 - redis_key
    const REDIS_WAIT_USE_MINI_APP_ID_INFO = 'wait_use_mini_app_info';     // 等待使用的小程序码信息 - redis_key
    const REDIS_WAIT_USE_MINI_APP_ID_LIST = 'wait_user_mini_app_list';    // 等待使用的小程序码标识 - redis_key
    const REDIS_FAIL_MINI_APP_ID_LIST     = 'fail_mini_app_list';         // 小程序码生成失败的标识列表 - redis_key
    const REDIS_USE_MINI_APP_QR_NUM       = 'use_mini_app_qr_num';         // 小程序码已使用数量 - redis_key

    /**
     * 获取用户QR码图片路径：智能小程序
     * @param $userId
     * @param $type
     * @param array $extParams
     * @return array|mixed
     */
    public static function getSmartQRAliOss($userId, $type, $extParams = [])
    {
        //应用ID
        $appId = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
        //ticket的前缀
        $ticketPrefix = '';
        $paramInfo = [];
        if ($type == ParamMapModel::TYPE_AGENT) {
            //二维码识别后跳转地址类型
            $landingType = $extParams['lt'] ?? DssUserQrTicketModel::LANDING_TYPE_MINIAPP;
            $ticketPrefix = self::AGENT_TICKET_PREFIX;
            //获取学生转介绍学生二维码资源数据
            $paramInfo = [
                'c' => $extParams['c'] ?? "0",//渠道ID
                'a' => $extParams['a'] ?? "0",//活动ID
                'e' => $extParams['e'] ?? "0",//员工ID
                'p' => $extParams['p'] ?? "0",//海报ID：二维码智能出现在特殊指定的海报
                'lt' => $landingType,//二维码类型
            ];
        } elseif ($type == ParamMapModel::TYPE_STUDENT) {
            $landingType = $extParams['lt'] ?? DssUserQrTicketModel::LANDING_TYPE_MINIAPP;
            if (isset($extParams['c']) && !empty($extParams['c'])) {
                $paramInfo['c'] = (int)$extParams['c'];//渠道ID
            }
            if (isset($extParams['a']) && !empty($extParams['a'])) {
                $paramInfo['a'] = (int)$extParams['a'];//活动ID
            }
            if (isset($extParams['e']) && !empty($extParams['e'])) {
                $paramInfo['e'] = (int)$extParams['e'];//员工ID
            }
        } else {
            return '';
        }
        //检测二维码是否已存在
        $res = ParamMapModel::getQrUrl($userId, $appId, $type, $paramInfo);
        if (!empty($res['qr_url'])) {
            return $res;
        }
        //生成小程序码
        try {
            $userQrTicket = $ticketPrefix . RC4::encrypt($_ENV['COOKIE_SECURITY_KEY'], $type . "_" . $userId);
            $paramInfo['r'] = $userQrTicket;
            $params = array_merge($paramInfo, ['app_id' => $appId, 'type' => $type, 'user_id' => $userId]);
            if ($landingType == DssUserQrTicketModel::LANDING_TYPE_MINIAPP) {
                $qrData = PosterService::getMiniappQrImage($appId, $params);
            } else {
                $qrData = PosterService::getAgentLandingPageQrImage($appId, $params, $extParams['package_id'] ?? 0);
            }
            if (empty($qrData[0])) {
                return '';
            }
            //记录二维码图片地址数据:学生的图片数据不在这里记录
            if ($type == ParamMapModel::TYPE_AGENT) {
                ParamMapModel::updateParamInfoQrUrl($qrData[1], $qrData[0]);
            }
        } catch (\Exception $e) {
            SimpleLogger::error('make agent qr image exception', [print_r($e->getMessage(), true)]);
            return '';
        }
        return ['qr_url' => $qrData[0], 'id' => $qrData[1], 'qr_ticket' => $userQrTicket, 'type' => $type, 'user_id' => $userId];
    }

    /**
     * 生产待使用的微信小程序码标识
     * @return bool
     */
    public static function createMiniAppId()
    {
        // 设置锁 - 同一时间只能有一个脚本执行
        $lock = Util::setLock(self::REDIS_CREATE_MINI_APP_ID_LOCK);
        if (!$lock) {
            SimpleLogger::info("createMiniAppId is lock", []);
            return false;
        }

        try {
            // 获取配置
            list($createIdNum, $secondNum, $waitMaxNum) = DictConstants::get(DictConstants::MINI_APP_QR, [
                'create_id_num',                // 需要生成的数量
                'get_mini_app_qr_second_num',   // 每秒请求数量 - 计算消息队列的延迟
                'wait_mini_qr_max_num',         // 待使用的小程序码最大数量
            ]);
            SimpleLogger::info("createMiniAppId start", [$secondNum]);
            // 检查配置
            if (empty($secondNum) || empty($createIdNum)) {
                SimpleLogger::info("createMiniAppId config error", [$secondNum, $createIdNum]);
                return false;
            }
            // 如果redis里面总数超过指定数量不继续生产 - 防止生成过多待使用数据
            $redis = RedisDB::getConn();
            $waitRedisNum = $redis->scard(self::REDIS_WAIT_USE_MINI_APP_ID_LIST);
            if ($waitRedisNum >= $waitMaxNum) {
                SimpleLogger::info("createMiniAppId redis wait num max", [$createIdNum, $secondNum, $waitMaxNum, $waitRedisNum]);
                return false;
            }

            SimpleLogger::info("createMiniAppId get wait use qr id", []);

            // 获取待使用的二维码标识，并放入到待生成小程序码的队列
            $qrIdArr = QrInfoService::getWaitUseQrId($createIdNum);
            $sendQueueArr = [];
            foreach ($qrIdArr as $_qrId) {
                $sendQueueArr[] = ['mini_app_qr_id' => $_qrId];
            }
            unset($_qrId);

            SimpleLogger::info("createMiniAppId send queue wait use qr id", []);

            // 放入队列
            QueueService::sendWaitCreateMiniAppQrId($sendQueueArr, 0, $secondNum);
            unset($sendQueueArr);

            SimpleLogger::info("createMiniAppId end success", []);
        } catch (RunTimeException $e) {
            SimpleLogger::error("createMiniAppId exception error", ['err' => $e->getMessage()]);
            return false;
        } finally {
            // 释放锁
            Util::unLock(self::REDIS_CREATE_MINI_APP_ID_LOCK);
        }
        return true;
    }

    /**
     * 检查qr_id是否已经存在
     * @param $qrId
     * @return bool
     */
    public static function checkQrIdExists($qrId)
    {
        $redis = RedisDB::getConn();
        $isRedisExists = $redis->sismember(self::REDIS_WAIT_USE_MINI_APP_ID_LIST, $qrId);
        if (!empty($isRedisExists)) {
            SimpleLogger::error("checkQrIdExists mini app qr id is redis exists", ['qr_id' => $qrId]);
            return true;
        }
        $isChExists = QrInfoOpCHModel::getQrInfoById($qrId, ['qr_path']);
        if (!empty($isChExists)) {
            SimpleLogger::error("checkQrIdExists mini app qr id is ch exists", ['qr_id' => $qrId]);
            return true;
        }
        return false;
    }
    /**
     * 获取小程序码图片
     * @param $data
     * @return false
     * @throws RunTimeException
     */
    public static function getMiniAppQr($data)
    {
        $miniAppQrId = $data['mini_app_qr_id'] ?? '';
        if (empty($miniAppQrId)) {
            SimpleLogger::info("getMiniAppQr is id empty", ['data' => $data]);
            return false;
        }
        $redis = RedisDB::getConn();
        // 查询CH和REDIS是否存在， 存在放弃
        $isExists = self::checkQrIdExists($miniAppQrId);
        if (!empty($isExists)) {
            SimpleLogger::info("getMiniAppQr is qr_id is exists", ['data' => $data]);
            return false;
        }

        // 发起请求微信接口 2秒超时
        $qrImageInfo = self::getMiniAppQrImage($miniAppQrId);
        if (!empty($qrImageInfo['qr_path'])) {
            // 成功： 写入redis待使用队列， 以及redis小程序码详细信息的hash
            $redis->hset(self::REDIS_WAIT_USE_MINI_APP_ID_INFO, $miniAppQrId, json_encode(['qr_path' => $qrImageInfo['qr_path']]));
            $redis->sadd(self::REDIS_WAIT_USE_MINI_APP_ID_LIST, [$miniAppQrId]);
            // 从失败队列里移除
            $redis->hdel(self::REDIS_FAIL_MINI_APP_ID_LIST, $miniAppQrId);
        } else {
            SimpleLogger::info("getMiniAppQr is error; retry info", ['data' => $data]);
            // 失败： 失败次数+1 用 hash  id=>失败次数，  同时根据失败次数计算延时时间单位秒
            // 失败1次，增加 失败次数*15 秒的队列延迟， 失败10次放弃这个miniAppId
            $failNum  = $redis->hget(self::REDIS_FAIL_MINI_APP_ID_LIST, $miniAppQrId);
            if (intval($failNum) < 10) {
                $failNum +=1;
                QueueService::sendWaitCreateMiniAppQrId([
                    [
                        'mini_app_qr_id' => $miniAppQrId,
                    ]
                ], $failNum * 15);
                $redis->hincrby(self::REDIS_FAIL_MINI_APP_ID_LIST, $miniAppQrId, 1);
            }
        }
        return true;
    }

    /**
     * 获取小程序标识对应的小程序码图片路径
     * @param $miniAppQrId
     * @param int $appId
     * @param int $busiType
     * @return array
     * @throws RunTimeException
     */
    public static function getMiniAppQrImage($miniAppQrId, $appId = Constants::SMART_APP_ID, $busiType = DssUserWeiXinModel::BUSI_TYPE_REFERRAL_MINAPP)
    {
        $qrImageInfo = [
            'qr_path' => '',
            'qr_id' => $miniAppQrId,
        ];
        if (empty($miniAppQrId)) {
            SimpleLogger::error('getMiniAppQrImage qr id is empty', [$appId, $busiType, $miniAppQrId]);
            return $qrImageInfo;
        }

        $wechat    = WeChatMiniPro::factory($appId, $busiType);
        if (empty($wechat)) {
            SimpleLogger::error('getMiniAppQrImage wechat create fail', [$appId, $busiType, $miniAppQrId]);
            return $qrImageInfo;
        }
        // 请求微信，获取小程序码图片
        $res = $wechat->getMiniAppImage($miniAppQrId);
        if ($res === false) {
            SimpleLogger::error('getMiniAppQrImage wechat get qr image error', [$appId, $busiType, $miniAppQrId, $res]);
            return $qrImageInfo;
        }

        SimpleLogger::error('getMiniAppQrImage download img start ', [$appId, $busiType, $miniAppQrId, $res]);
        $tmpFileFullPath = $_ENV['STATIC_FILE_SAVE_PATH'] . '/' . $miniAppQrId . '.jpg';
        if (file_exists($tmpFileFullPath)) {
            chmod($tmpFileFullPath, 0755);
        }

        $bytesWrite = file_put_contents($tmpFileFullPath, $res);
        if (empty($bytesWrite)) {
            SimpleLogger::error('save miniapp code image file error', [$miniAppQrId]);
            return $qrImageInfo;
        }

        SimpleLogger::error('getMiniAppQrImage upload img start ', [$appId, $busiType, $miniAppQrId, $res]);
        $imagePath = $_ENV['ENV_NAME'] . '/' . AliOSS::DIR_MINIAPP_CODE . '/' . $miniAppQrId . ".jpg";
        AliOSS::uploadFile($imagePath, $tmpFileFullPath);
        unlink($tmpFileFullPath);

        $qrImageInfo['qr_path'] = $imagePath;
        return $qrImageInfo;
    }

    /**
     * 获取一个用户的小程序码对应的qr_id
     * @param $userId
     * @param $userType
     * @param $channel
     * @param $landingType
     * @param array $extParams
     * @return string[]
     */
    public static function getUserMiniAppQr($userId, $userType, $channel, $landingType, array $extParams = [])
    {
        $qrInfo = [
            'qr_path' => '',
            'qr_id' => '',
        ];
        $qrData = [
            'qr_id'        => "",
            'qr_path'      => "",
            'qr_sign'      => "",
            'user_id'      => $userId,
            'user_type'    => $userType,
            'channel_id'   => $channel,
            'landing_type' => $landingType,
            'activity_id'  => $extParams['activity_id'] ?? 0,
            'employee_id'  => $extParams['employee_id'] ?? 0,
            'poster_id'    => $extParams['poster_id'] ?? 0,
            'app_id'       => $extParams['app_id'] ?? Constants::SMART_APP_ID,
            'busies_type'  => $extParams['busies_type'] ?? DssUserWeiXinModel::BUSI_TYPE_REFERRAL_MINAPP,
            'user_status'  => $extParams['user_status'] ?? ($extParams['user_current_status'] ??  0),
        ];
        // 根据小程序码主要信息，查询CH
        $qrSign = QrInfoService::createQrSign($qrData);
        $qrImage = QrInfoOpCHModel::getQrInfoBySign($qrSign, ['qr_path', 'qr_id'])[0] ?? [];
        // CH查到直接返回qr_path, qr_id
        if (!empty($qrImage) && AliOSS::doesObjectExist($qrImage['qr_path'])) {
            $qrInfo['qr_path'] = $qrImage['qr_path'];
            $qrInfo['qr_id'] = $qrImage['qr_id'];
            return $qrInfo;
        }
        // CH没有查到获取一个待使用的小程序码信息
        $redis = RedisDB::getConn();
        $qrInfo['qr_id'] = $redis->spop(self::REDIS_WAIT_USE_MINI_APP_ID_LIST);
        // 如果qr_id 为空，需要立即启动生成小程序
        if (empty($qrInfo['qr_id'])) {
            QueueService::startCreateMiniAppId();
            Util::errorCapture("getUserMiniAppQr error is redis empty", []);
            return $qrInfo;
        }

        $miniAppIdInfo = $redis->hget(self::REDIS_WAIT_USE_MINI_APP_ID_INFO, $qrInfo['qr_id']);
        $miniAppIdInfo = json_decode($miniAppIdInfo, true);
        $qrInfo['qr_path'] = $miniAppIdInfo['qr_path'];

        // 把小程序码和信息关联并且写入到CH
        $qrData['qr_id'] = $qrInfo['qr_id'];
        $qrData['qr_path'] = $qrInfo['qr_path'];
        $qrData['qr_sign'] = $qrSign;
        QrInfoOpCHModel::saveQrInfo([$qrData]);

        // 更新小程序码使用数量
        $useQrNum = $redis->incr(self::REDIS_USE_MINI_APP_QR_NUM);

        // 如果达到阀值 启用生成小程序码标识队列
        $num = DictConstants::get(DictConstants::MINI_APP_QR, 'start_generation_threshold_num');
        if (fmod($useQrNum, $num) == 0) {
            QueueService::startCreateMiniAppId();
        }

        // 清理缓存
        $redis->hdel(self::REDIS_WAIT_USE_MINI_APP_ID_INFO, [$qrInfo['qr_id']]);

        return $qrInfo;
    }

    /**
     * 批量获取用户的小程序码对应的qr_id
     * @param array $qrParams
     * @param bool $isFullUrl
     * @return array
     */
    public static function getUserMiniAppQrList(array $qrParams = [], $isFullUrl = false)
    {
        $returnQrSignArr = [];
        $saveQrData = [];
        // 根据小程序码主要信息，查询CH
        $qrImageArr = QrInfoOpCHModel::getQrInfoBySign(array_column($qrParams, 'qr_sign'), ['qr_path', 'qr_id', 'qr_sign']);
        $qrSignData = array_column($qrImageArr, null, 'qr_sign');
        $redis = RedisDB::getConn();
        SimpleLogger::info("getUserMiniAppQrList qrSignData", [$qrImageArr, $qrSignData]);
        foreach ($qrParams as $_qrParam) {
            $_qrSign = $_qrParam['qr_sign'];
            if (isset($qrSignData[$_qrSign])) {
                $returnQrSignArr[$_qrSign] = [
                    'qr_id' => $qrSignData[$_qrSign]['qr_id'],
                    'qr_path' => $isFullUrl ? AliOSS::replaceCdnDomainForDss($qrSignData[$_qrSign]['qr_path']) : $qrSignData[$_qrSign]['qr_path'],
                ];
            } else {
                // CH没有查到获取一个待使用的小程序码信息
                $qrId = $redis->spop(self::REDIS_WAIT_USE_MINI_APP_ID_LIST);
                // 如果qr_id 为空，需要立即启动生成小程序
                if (empty($qrId)) {
                    QueueService::startCreateMiniAppId();
                    Util::errorCapture("getUserMiniAppQr error is redis empty", []);
                    return $returnQrSignArr;
                }

                $miniAppIdInfo = $redis->hget(self::REDIS_WAIT_USE_MINI_APP_ID_INFO, $qrId);
                $miniAppIdInfo = json_decode($miniAppIdInfo, true);
                // 更新小程序码使用数量
                $useQrNum = $redis->incr(self::REDIS_USE_MINI_APP_QR_NUM);
                // 如果达到阀值 启用生成小程序码标识队列
                $num = DictConstants::get(DictConstants::MINI_APP_QR, 'start_generation_threshold_num');
                if (fmod($useQrNum, $num) == 0) {
                    QueueService::startCreateMiniAppId();
                }
                // 清理缓存
                $redis->hdel(self::REDIS_WAIT_USE_MINI_APP_ID_INFO, [$qrId]);

                // 把小程序码和信息关联并且写入到CH
                $saveQrData[] = [
                    'qr_id'        => $qrId,
                    'qr_path'      => $miniAppIdInfo['qr_path'],
                    'qr_sign'      => $_qrSign,
                    'user_id'      => $_qrParam['user_id'],
                    'user_type'    => $_qrParam['user_type'],
                    'channel_id'   => $_qrParam['channel_id'],
                    'landing_type' => $_qrParam['landing_type'],
                    'activity_id'  => $_qrParam['activity_id'] ?? 0,
                    'employee_id'  => $_qrParam['employee_id'] ?? 0,
                    'poster_id'    => $_qrParam['poster_id'] ?? 0,
                    'app_id'       => $_qrParam['app_id'] ?? Constants::SMART_APP_ID,
                    'busies_type'  => $_qrParam['busies_type'] ?? DssUserWeiXinModel::BUSI_TYPE_REFERRAL_MINAPP,
                    'user_status'  => $_qrParam['user_status'] ?? ($_qrParam['user_current_status'] ??  0),
                ];
                $returnQrSignArr[$_qrSign] = [
                    'qr_id' => $qrId,
                    'qr_path' => $isFullUrl ? AliOSS::replaceCdnDomainForDss($miniAppIdInfo['qr_path']) : $miniAppIdInfo['qr_path'],
                ];
            }
        }
        QrInfoOpCHModel::saveQrInfo($saveQrData);
        return $returnQrSignArr;
    }

    /**
     * 获取二维码id的详情
     * @param $qrId
     * @param string[] $fileds
     * @return array
     */
    public static function getQrInfoById($qrId, $fileds = [], $extendArr = [])
    {
        $qrInfo = QrInfoOpCHModel::getQrInfoById($qrId, $fileds);
        if (empty($qrInfo)) {
            return [];
        }
        // 兼容原有方法使用字段名称的统一
        $qrInfo['param_id'] = $qrInfo['qr_id'] ?? 0;
        $qrInfo['type'] = $qrInfo['user_type'] ?? 0;
        $qrInfo['r'] = $qrInfo['qr_ticket'] ?? "";
        $qrInfo['c'] = $qrInfo['channel_id'] ?? 0;
        $qrInfo['e'] = $qrInfo['employee_id'] ?? 0;
        $qrInfo['a'] = $qrInfo['activity_id'] ?? 0;
        $qrInfo['p'] = $qrInfo['poster_id'] ?? 0;
        $qrInfo['user_current_status'] = $qrInfo['user_status'] ?? 0;
        $qrInfo['id'] = $qrInfo['qr_id'] ?? 0;
        $qrData = !empty($qrInfo['qr_data']) ? json_decode($qrInfo['qr_data'], true) : [];
        return array_merge($qrData, $qrInfo, $extendArr);
    }
}
