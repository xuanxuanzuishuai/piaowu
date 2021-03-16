<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021-03-08
 * Time: 14:39
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Erp;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Erp\ErpGiftGroupV1Model;
use App\Models\Erp\ErpStudentOrderV1Model;

class ErpOrderV1Service
{

    /**
     * 判断产品包是否绑定赠品组
     * @param $packageId
     * @return mixed
     */
    public static function getBoundGiftGroup($packageId)
    {
        return ErpGiftGroupV1Model::getGiftGroupsByPackageId($packageId);
    }

    /**
     * 获取默认地址
     * @param $studentUuid
     * @return array
     */
    public static function getStudentDefaultAddress($studentUuid)
    {
        $erp = new Erp();
        $studentAddress = $erp->getStudentAddressList($studentUuid);
        $defaultAddress = [];
        if (!empty($studentAddress) && $studentAddress['code'] == Valid::CODE_SUCCESS) {
            foreach ($studentAddress['data']['list'] as $sa) {
                if ($sa['default'] == 1) {
                    $defaultAddress = $sa;
                }
            }
        }
        return $defaultAddress;
    }
    /**
     * 判断产品包下是否绑定赠品组
     * @param $packageId
     * @return bool
     */
    public static function haveBoundGiftGroup($packageId)
    {
        $record = ErpGiftGroupV1Model::getGiftGroupsByPackageId($packageId);
        return !empty($record);
    }

    /**
     * @param $packageId
     * @param $student
     * @param $payChannel
     * @param $payType
     * @param $employeeUuid
     * @param $channel
     * @param array $giftGoods
     * @param null | string $callback 指定的支付宝回调地址
     * @return array|bool
     */
    public static function createOrder($packageId, $student, $payChannel, $payType, $employeeUuid, $channel, $giftGoods = [], $callback = null)
    {
        $studentId = $student['user_id'];
        if (DssGiftCodeModel::hadPurchasePackageByType($studentId)) {
            SimpleLogger::error('has_trialed', ['student_id' => $studentId]);
            return Valid::addAppErrors([], 'has_trialed');
        }

        $callbacks  = self::callbacks($channel, $payChannel);
        $successUrl = $callback ?? $callbacks['success_url'];
        $cancelUrl  = $callback ?? $callbacks['cancel_url'];

        // create bill
        $erp = new Erp();
        $result = $erp->createBillV1([
            'uuid'          => $student['uuid'],
            'app_id'        => Constants::SMART_APP_ID,
            'address_id'    => $student['address_id'], // 地址
            'open_id'       => $student['open_id'],
            'package_id'    => $packageId, // 产品包id
            'channel'       => $channel,
            'sale_shop'     => DssErpPackageV1Model::SALE_SHOP_AI_PLAY,
            'pay_type'      => $payType, // 支付类型 1 直付 2 账户
            'pay_channel'   => $payChannel, // 三方支付渠道
            'success_url'   => $successUrl ?? null, // 支付宝web 支付成功跳转链接
            'cancel_url'    => $cancelUrl ?? null, // 支付宝web 支付失败跳转链接
            'result_url'    => $callbacks['result_url'] ?? null, // 微信H5 支付结果跳转链接
            'employee_uuid' => $employeeUuid, // 成单人
            'gift_goods'    => $giftGoods, //选择的赠品
        ]);
        if (empty($result) || !empty($result['code'])) {
            return Valid::addAppErrors([], 'create_bill_error');
        }
        return $result['data'] ?? [];
    }

    public static function callbacks($channel, $payChannel)
    {
        $successUrl = null;
        $resultUrl = null;
        $cancelUrl = null;

        return [
            'success_url' => $successUrl,
            'result_url'  => $resultUrl,
            'cancel_url'  => $cancelUrl,
        ];
    }

    /**
     * 根据订单id获取订单信息
     * @param $orderId
     * @return array|mixed
     */
    public static function getOrderInfo($orderId)
    {
        $orderData = ErpStudentOrderV1Model::getRecord(['id' => $orderId]);
        return $orderData ?? [];
    }
}
