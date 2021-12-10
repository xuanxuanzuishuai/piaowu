<?php
/**
 * 二维码标识生成获取使用
 */

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\QrInfoOpCHModel;
use App\Services\Queue\QueueService;

class QrInfoService
{
    private const REDIS_WAIT_USE_QR_SET   = 'wait_use_qr_set_new';    // 等待使用的二维码标识
    const         REDIS_CREATE_QR_ID_LOCK = 'create_qr_id_lock_new';    // 生成二维码码标识锁

    /**
     * 生成qr_sign
     * @param $qrData
     * @param $appId
     * @param $busiesType
     * @return string
     * @throws RunTimeException
     */
    public static function createQrSign($qrData, $appId, $busiesType): string
    {
        $createTicketData = [];
        $signField = [
            'user_id'             => 'user_id',                 // 用户id
            'user_type'           => 'user_type',               // 用户类型
            'landing_type'        => 'landing_type',            // landing页类型
            'channel_id'          => 'channel_id',              // 渠道id
            'activity_id'         => 'activity_id',             // 活动id
            'employee_id'         => 'employee_id',             // 员工id
            'poster_id'           => 'poster_id',               // 海报id
            'user_current_status' => 'user_current_status',     // 用户当前状态 （兼容老数据，再使用时可以用user_status）
            'user_status'         => 'user_status',             // 用户当前状态，优先级高于user_current_status
            'create_type'         => 'create_type',             // 创建类型
            'qr_type'             => 'qr_type',                 // 二维码类型
            'invited_mobile'      => 'invited_mobile',          // 受邀人手机号
            'date'                => 'date',                    // 日期
            'check_active_id'     => 'check_active_id',         // 海报唯一码校验活动ID
            'from_service'        => 'from_service',            // 请求来源服务
            'employee_uuid'       => 'employee_uuid',           // 员工uuid
        ];
        $qrData['check_active_id'] = PosterService::getCheckActivityId($appId, $qrData['user_id'] ?? 0);
        foreach ($signField as $paramsFiled => $createField) {
            if (isset($qrData[$paramsFiled]) && !Util::emptyExceptZero($qrData[$paramsFiled])) {
                $createTicketData[$createField] = $qrData[$paramsFiled];
            }
        }
        if (empty($createTicketData)) {
            throw new RunTimeException(["create_sign_error"], [$qrData, $appId, $busiesType]);
        }
        // $createTicketData['app_id']      = $appId;           // 业务id
        // $createTicketData['busies_type'] = $busiesType; // 场景id

        ksort($createTicketData);
        $paramsStr = http_build_query($createTicketData);
        return md5($paramsStr . '_appId_' . $appId . '_busiesType_' . $busiesType);
    }


    /**
     * 获取指定数量的待使用的qr_id
     * @param $getQrNum
     * @param int $tryNum
     * @return mixed
     */
    public static function getWaitUseQrId($getQrNum, int $tryNum = 0)
    {
        $redis = RedisDB::getConn();
        // 获取待使用总数， 如果总数低于需要的数量 启用生成标识对了， 并且延时1秒再次获取，最多重试3次，超过3次有多少返回多少
        $num = $redis->scard(self::REDIS_WAIT_USE_QR_SET);
        if ($num < $getQrNum) {
            // 只启用一次生成服务即可
            if ($tryNum == 0) {
                QueueService::startCreateWaitUseQrId(['is_qr_empty' => 'true']);
                Util::errorCapture("getUserMiniAppQr error is redis empty", []);
            }

            // 重试3次，每次等待1秒
            if ($tryNum < 3) {
                sleep(1);
                $tryNum += 1;
                return self::getWaitUseQrId($getQrNum, $tryNum);
            }
        }

        // 获取待使用的标识数量
        $qrIdArr = $redis->spop(self::REDIS_WAIT_USE_QR_SET, $getQrNum);
        // 如果达到阀值(已使用数量-每次生成数量-10000) 启用生成标识队列
        // 启用数量计算 :: 剩余数量 < 0.2*300000
        $createUseNum = DictConstants::get(DictConstants::MINI_APP_QR, 'create_wait_use_qr_num');
        if (($num - $getQrNum) <= 0.4 * $createUseNum) {
            QueueService::startCreateWaitUseQrId();
        }

        return $getQrNum == 1 ? ($qrIdArr[0] ?? '') : $qrIdArr;
    }

    /**
     * 生产待使用的二维码码标识
     * @param $params
     * @return bool
     */
    public static function createQrId($params): bool
    {
        // 设置锁 - 同一时间只能有一个脚本执行
        $lock = Util::setLock(self::REDIS_CREATE_QR_ID_LOCK);
        if (!$lock) {
            SimpleLogger::info("createQrId is lock", [$params]);
            return false;
        }

        try {
            // 获取配置
            list($maxId, $createIdNum, $waitMaxNum) = DictConstants::get(DictConstants::MINI_APP_QR, [
                'current_max_id',               // 当前已用的最大标识
                'create_wait_use_qr_num',       // 每次生成待使用的二维码码数量
                'wait_use_qr_max_num',          // 待使用的二维码码最大数量
            ]);
            SimpleLogger::info("createQrId start", [$maxId, $createIdNum, $params]);
            // 检查配置
            if (empty($maxId) || empty($createIdNum)) {
                SimpleLogger::info("createQrId config error", [$maxId, $createIdNum]);
                return false;
            }
            // 如果redis里面总数超过指定数量不继续生产 - 防止生成过多待使用数据
            $redis        = RedisDB::getConn();
            $waitRedisNum = $redis->scard(self::REDIS_WAIT_USE_QR_SET);
            if ($waitRedisNum >= $waitMaxNum) {
                SimpleLogger::info("createQrId redis wait num max", [$maxId, $createIdNum, $waitMaxNum, $waitRedisNum]);
                return false;
            }

            // 开始生成qr_id，并放入到待使用的集合
            $sortId       = $maxId;
            $sendQueueArr = [];
            for ($i = 0; $i < $createIdNum; $i++) {
                $sortId = Util::getIncrSortId($sortId);
                // 如果全是数字跳过
//                if (preg_match('/^[0-9]+$/', $sortId)) {
//                    continue;
//                }
                $sendQueueArr[] = $sortId;
                if ($i % 1000 == 0) {
                    $redis->sadd(self::REDIS_WAIT_USE_QR_SET, $sendQueueArr);
                    $sendQueueArr = [];
                }
            }
            if (!empty($sendQueueArr)) {
                $redis->sadd(self::REDIS_WAIT_USE_QR_SET, $sendQueueArr);
                unset($sendQueueArr);
            }

            // 更新最大值 - 记录最后一次生成qr_id
            $updateRes = DictService::updateValue(DictConstants::MINI_APP_QR['type'], 'current_max_id', $sortId);
            if (empty($updateRes)) {
                SimpleLogger::error("createQrId update current_max_id error", ['current_max_id' => $sortId]);
                Util::errorCapture("createQrId update current_max_id error", ['current_max_id' => $sortId]);
                throw new RunTimeException(["createQrId_update_current_max_id_error"], ['current_max_id' => $sortId]);
            }

            // 更新缓存
            DictConstants::get(DictConstants::MINI_APP_QR, ['current_max_id']);

            // 日志记录最大id
            SimpleLogger::info("createQrId end success", ['current_max_id' => $sortId]);
        } catch (RunTimeException $e) {
            SimpleLogger::error("createQrId exception error", ['err' => $e->getMessage()]);
            return false;
        } finally {
            // 释放锁
            Util::unLock(self::REDIS_CREATE_QR_ID_LOCK);
        }
        return true;
    }


    /**
     * 生成数据对应的qr_id - 支持批量 - 普通二维码需要传入qr_path
     * @param  $appId
     * @param  $busiesType
     * @param array $qrParams
     * @param array $field
     * @param bool $isFullUrl
     * @return array
     * @throws RunTimeException
     * 使用场景和注意事项
     * --------- 可使用场景 --------
     * 1.生成无二维码的qr_id时 - 可使用
     * 2.生成h5二维码对应的qr_id时(h5二维码需要自己生成) - 可使用
     * 3.注意qr_type值，无二维码qr_type=3, h5二维码 qr_type=2
     * -------- 不可使用场景 --------
     * 1.生成小程序专属二维码 - 不可使用
     */
    public static function getQrIdList($appId, $busiesType, array $qrParams, array $field = [], bool $isFullUrl = false): array
    {
        $returnQrSignArr = [];
        $saveQrData      = [];

        if (empty($qrParams)) {
            return $returnQrSignArr;
        }
        // 生成qr_sign, qr_ticket
        $qrSignArr = [];
        foreach ($qrParams as &$item) {
            $sign                   = self::createQrSign($item, $appId, $busiesType);
            $qrSignArr[]            = $sign;
            $item['qr_sign']        = $sign;
            $returnQrSignArr[$sign] = [];
        }
        unset($item);

        $selectField = array_merge($field, ['qr_path', 'qr_id', 'qr_sign', 'qr_ticket']);
        //获取海报自动审核校验活动ID
        $checkActiveId = PosterService::getCheckActivityId($appId, $qrParams[0]['user_id'] ?? 0);
        // 查询ch
        $qrImageArr = QrInfoOpCHModel::getQrInfoBySign($qrSignArr, $selectField);
        $qrSignData = array_column($qrImageArr, null, 'qr_sign');
        // 获取待使用的qr_id
        foreach ($qrParams as $_qrParam) {
            $_qrSign = $_qrParam['qr_sign'];
            if (isset($qrSignData[$_qrSign])) {
                $_tmp = $qrSignData[$_qrSign];
            } else {
                /** CH没有查到获取一个待使用的小程序码信息 */
                // 获取一个空白待使用的qr_id
                $qrId = self::getWaitUseQrId(1);
                // 把信息关联并且写入到CH - 补全数据
                $_tmpSaveData                    = $_qrParam;
                $_tmpSaveData['qr_id']           = $qrId;
                $_tmpSaveData['qr_path']         = $_qrParam['qr_path'] ?? '';
                $_tmpSaveData['qr_sign']         = $_qrSign;
                $_tmpSaveData['app_id']          = $appId;
                $_tmpSaveData['busies_type']     = $busiesType;
                $_tmpSaveData['user_status']     = $_qrParam['user_status'] ?? ($_qrParam['user_current_status'] ?? 0);
                $_tmpSaveData['create_type']     = $_qrParam['create_type'] ?? DictConstants::get(DictConstants::TRANSFER_RELATION_CONFIG, 'user_relation_status');
                $_tmpSaveData['qr_type']         = $_qrParam['qr_type'] ?? DictConstants::get(DictConstants::MINI_APP_QR, 'qr_type_none');
                $_tmpSaveData['check_active_id'] = $checkActiveId;
                $_tmpSaveData['date']            = date('Y-m-d', time());
                $saveQrData[]                    = $_tmpSaveData;

                // 把必要的信息直接整理好返回给调用者 - 不需要再查询数据库
                $_tmp = [];
                foreach ($selectField as $_sf) {
                    if (isset($_tmpSaveData[$_sf])) {
                        $_tmp[$_sf] = $_tmpSaveData[$_sf];
                    } else {
                        $_tmp[$_sf] = '';
                    }
                }
                unset($_sf);
            }
            $_tmp['qr_full_path'] = '';
            $isFullUrl && $_tmp['qr_full_path'] = AliOSS::replaceCdnDomainForDss($_tmp['qr_path']);
            $returnQrSignArr[$_qrSign] = $_tmp;
        }

        // 如果数据不为空再写入ch
        if (!empty($saveQrData)) {
            $res = QrInfoOpCHModel::saveQrInfo($saveQrData);
            if (empty($res)) {
                return [];
            }
        }

        // 去掉key - 返回 [qr_id,qr_sign,qr_ticket,$field]
        return array_values($returnQrSignArr);
    }
}
