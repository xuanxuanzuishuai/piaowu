<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021-03-08
 * Time: 14:39
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\Dss\DssCategoryV1Model;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Erp\ErpGiftGroupV1Model;
use App\Models\Erp\ErpPackageV1Model;
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
     * @throws RunTimeException
     */
    public static function createOrder($packageId, $student, $payChannel, $payType, $employeeUuid, $channel, $giftGoods = [], $callback = null)
    {
        $studentId = $student['id'];
        if ($student['package_sub_type'] == DssCategoryV1Model::DURATION_TYPE_TRAIL
            && DssGiftCodeModel::hadPurchasePackageByType($studentId, DssPackageExtModel::PACKAGE_TYPE_TRIAL, false)) {
            SimpleLogger::error('has_trialed', ['student_id' => $studentId]);
            throw new RunTimeException(['has_trialed']);
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
            SimpleLogger::error('CREATE BILL ERROR', [$result]);
            throw new RunTimeException(['create_bill_error', '', '', [':'.$result['errors'][0]['err_msg']]]);
        }
        return $result['data'] ?? [];
    }

    public static function callbacks($channel, $payChannel)
    {
        $successUrl = null;
        $resultUrl = null;
        $cancelUrl = null;
        // 体验&年卡：
        // H5
        // weixin不调用支付宝
        // https://referral.xiaoyezi.com/operation/landing/pay?
        list($successUrl, $cancelUrl, $resultUrl) = DictConstants::getValues(
            DictConstants::AGENT_WEB_STUDENT_CONFIG,
            ['success_url', 'cancel_url', 'result_url']
        );

        // 微信调用支付宝
        // 体验&年卡
        // https://referral.xiaoyezi.com/operation/landing/whechatali?
        if ($channel == ErpPackageV1Model::CHANNEL_WX
        && $payChannel == PayServices::PAY_CHANNEL_V1_ALIPAY) {
            list($successUrl, $cancelUrl, $resultUrl) = DictConstants::getValues(
                DictConstants::WEIXIN_ALIPAY_CONFIG,
                ['success_url', 'cancel_url', 'result_url']
            );
        }

        // 代理回调：
        if ($channel == ErpPackageV1Model::CHANNEL_OP_AGENT) {
            if ($payChannel == PayServices::PAY_CHANNEL_V1_ALIPAY && Util::isWx()) {
                list($successUrl, $cancelUrl, $resultUrl) = DictConstants::getValues(
                    DictConstants::WEIXIN_ALIPAY_CONFIG,
                    ['success_url', 'cancel_url', 'result_url']
                );
            } else {
                // https://referral.xiaoyezi.com/operation/pay?
                list($successUrl, $cancelUrl, $resultUrl) = DictConstants::getValues(
                    DictConstants::AGENT_WEB_STUDENT_CONFIG,
                    ['success_url_v1', 'cancel_url_v1', 'result_url_v1']
                );
            }
        }

        return [
            'success_url' => $successUrl,
            'result_url' => $resultUrl,
            'cancel_url' => $cancelUrl,
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
