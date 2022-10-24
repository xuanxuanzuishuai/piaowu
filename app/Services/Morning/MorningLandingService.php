<?php
/**
 * Created by PhpStorm.
 * User: xingkuiYu
 * Date: 2021/9/7
 * Time: 14:55
 */

namespace App\Services\Morning;


use App\Libs\Constants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\AdTrack\PurchaseLog;
use App\Services\ErpOrderV1Service;
use App\Services\Queue\QueueService;

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
        $orderInfo = PurchaseLog::getRecord([
            'app_id'     => Constants::QC_APP_ID,
            'uuid'       => $params['uuid'],
            'package_id' => $params['package_id'],
        ], ['id', 'order_id']);

        if (empty($orderInfo['order_id'])) {
            throw new RunTimeException(['order_not_exist']);
        }

        //获取订单地址信息
        $orderKind = self::getOrderInfo($orderInfo['order_id']);
        $orderRecord = ErpOrderV1Service::getOrderInfo($orderInfo['order_id']);
        $data = [
            'uuid'              => $params['uuid'],
            'order_id'          => $orderInfo['order_id'],
            'contain_entity'    => $orderKind,
            'address_completed' => $orderRecord['student_addr_id'] ? true : false,
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
     * 生成用户的临时码
     * @param $uuid
     * @return array
     */
    public static function genTemporaryCode($uuid)
    {
        $temporaryCode = $code = (string)rand(1000, 9999);
        $key = 'qc_landing_' . $uuid;
        $maxExpireTime = Util::TIMESTAMP_ONEWEEK;
        $redis = RedisDB::getConn();
        $redis->setex($key, $maxExpireTime, $temporaryCode);
        return [
            'uuid'           => $uuid,
            'temporary_code' => $temporaryCode,
        ];
    }

    /**
     * 移除临时码
     * @param $uuid
     * @return int
     */
    public static function removeTemporaryCode($uuid)
    {
        $redis = RedisDB::getConn();
        $key = 'qc_landing_' . $uuid;
        return $redis->del([$key]);
    }

    /**
     * 投递检查地址的延迟信息
     * @param $uuid
     * @return bool
     */
    public static function checkOrderAddress($uuid)
    {
        $packageId = '123';
        //获取订单信息
        $orderInfo = PurchaseLog::getRecord([
            'app_id'     => Constants::QC_APP_ID,
            'uuid'       => $uuid,
            'package_id' => $packageId,
        ], ['id', 'order_id']);

        if (empty($orderInfo['order_id'])) {
            SimpleLogger::info("order id not found", []);
            return false;
        }
        $data = [
            'uuid'       => $uuid,
            'package_id' => $packageId,
        ];
        QueueService::qcLandingOrderAddress($data);
        return true;
    }
}