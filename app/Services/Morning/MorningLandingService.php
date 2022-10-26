<?php
/**
 * Created by PhpStorm.
 * User: xingkuiYu
 * Date: 2021/9/7
 * Time: 14:55
 */

namespace App\Services\Morning;


use App\Libs\DictConstants;
use App\Libs\Dss;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\AdTrack\PurchaseLog;
use App\Models\Erp\ErpStudentModel;
use App\Services\DictService;
use App\Services\ErpOrderV1Service;
use App\Services\Queue\QueueService;
use App\Services\SendSmsService;

class MorningLandingService
{
    /**
     * 保存收货地址信息
     * @param $params
     * @return int|mixed
     */
    public static function modifyAddress($params)
    {
        $params['default'] = $params['is_default'];
        unset($params['is_default']);
        $result = (new Erp())->modifyStudentAddress($params);
        return $result['data']['address_id'] ?? 0;
    }

    /**
     * 更新订单发货地址
     * @param $orderId
     * @param $addressId
     * @return array|bool
     */
    public static function updateOrderAddress($orderId, $addressId)
    {
        $requestParams = [
            'order_id'   => $orderId,
            'address_id' => $addressId,
        ];
        return (new Erp())->updateOrderAddress($requestParams);
    }

    /**
     * 获取订单详情
     * @param $params
     * @return array
     * @throws RunTimeException
     */
    public static function getOrderDetail($params)
    {
        //获取订单信息
        $orderInfo = PurchaseLog::getTrailInfoByUuid($params['uuid']);
        if (empty($orderInfo) || !str_contains($orderInfo['ref'], 'landingbox01')) {
            $containEntity = false;
            $address_completed = false;
        } else {
            $containEntity = self::getOrderInfo($orderInfo['order_id']);
            $orderRecord = ErpOrderV1Service::getOrderInfo($orderInfo['order_id']);
            $address_completed = $orderRecord['student_addr_id'] ? true : false;
        }

        //获取订单地址信息
        $data = [
            'uuid'              => $params['uuid'],
            'order_id'          => $orderInfo['order_id'] ?? '',
            'contain_entity'    => $containEntity,
            'address_completed' => $address_completed,
            'expired'           => self::getTemporaryCode($params['uuid']) ? false : true,
        ];
        return $data ?? [];
    }

    /**
     * 判断订单是否包含实物
     * @param $orderId
     * @return mixed
     * @throws RunTimeException
     */
    public static function getOrderInfo($orderId)
    {
        //校验是否包含实物
        $res = (new Erp())->checkPackageHaveKind(['order_id' => $orderId]);
        if (!empty($res['code'])) {
            SimpleLogger::error('ERP REQUEST ERROR', [$res]);
            throw new RunTimeException(['request_error']);
        }
        return $res['data']['have_kind'];
    }

    /**
     * 获取临时码缓存key
     * @param $uuid
     * @return string
     */
    public static function getTemporaryCodeKey($uuid)
    {
        return 'qc_landing_' . $uuid;
    }

    /**
     * 生成临时码
     * @param $uuid
     * @return string
     */
    public static function genTemporaryCode($uuid)
    {
        $redis = RedisDB::getConn();
        $key = self::getTemporaryCodeKey($uuid);
        $temporaryCode = $redis->get($key);
        if (!empty($temporaryCode)) {
            return $temporaryCode;
        }

        $temporaryCode = $code = (string)rand(1000, 9999);
        $maxExpireTime = Util::TIMESTAMP_ONEWEEK;
        $redis->setex($key, $maxExpireTime, $temporaryCode);
        return $temporaryCode;
    }

    /**
     * 移除临时码
     * @param $uuid
     * @return int
     */
    public static function removeTemporaryCode($uuid)
    {
        $redis = RedisDB::getConn();
        $key = self::getTemporaryCodeKey($uuid);
        return $redis->del([$key]);
    }

    /**
     * 校验临时码是否有效
     * @param $uuid
     * @return bool
     */
    public static function getTemporaryCode($uuid)
    {
        $key = self::getTemporaryCodeKey($uuid);
        $redis = RedisDB::getConn();
        $temporaryCode = $redis->get($key);
        if (empty($temporaryCode)) {
            return '';
        }
        return $temporaryCode;
    }

    /**
     * 投递检查地址的延迟信息
     * @param $uuid
     * @param $packageId
     * @return bool
     */
    public static function checkOrderAddress($uuid, $packageId)
    {
        $data = [
            'uuid'       => $uuid,
            'package_id' => $packageId,
        ];
        QueueService::qcLandingOrderAddress($data);
        return true;
    }

    /**
     * 消费延迟5分钟的检查
     * @param $params
     * @return bool
     * @throws RunTimeException
     */
    public static function consumeCheckOrderAddress($params)
    {
        try {
            $res = self::getOrderDetail($params);
            if ($res['contain_entity'] && !$res['address_completed']) {
                $mobile = ErpStudentModel::getRecord(['uuid' => $params['uuid']], ['mobile']);
                $temporaryCode = self::genTemporaryCode($params['uuid']);
                $url = DictService::getKeyValue(DictConstants::QC_LANDING_CONFIG, 'collect_address_url');
                $paramsStr = '?uuid=' . $params['uuid'] . '&temporary_code=' . $temporaryCode;
                $fullUrl = $url . $paramsStr;
                $shortUrl = (new Dss())->getShortUrl($fullUrl);
                SendSmsService::sendQcLandingAddress($mobile['mobile'], [$shortUrl['data']['short_url']]);
            }
        }catch (\Exception $e){
            throw new RunTimeException([$e->getMessage()],[]);
        }
        return true;
    }
}