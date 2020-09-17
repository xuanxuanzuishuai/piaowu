<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/9/8
 * Time: 4:57 PM
 */

namespace App\Services\ErpServiceV1;

use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Valid;
use App\Models\ModelV1\ErpPackageV1Model;
use App\Services\PayServices;
use App\Libs\Exceptions\RunTimeException;


class ErpOrderV1Service
{

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

    public static function createOrder($packageId, $student, $payChannel, $payType, $employeeUuid, $channel)
    {
        $studentId = $student['id'];
        if (ErpPackageV1Service::isTrailPackage($packageId) && PayServices::hasTrialed($studentId)) {
            SimpleLogger::error('has_trialed', ['student_id' => $studentId]);
            return Valid::addAppErrors([], 'has_trialed');
        }

        $callbacks = self::callbacks($channel);

        // create bill
        $erp = new Erp();
        $result = $erp->createBillV1([
            'uuid' => $student['uuid'],
            'app_id' => UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            'address_id' => $student['address_id'], // 地址
            'open_id' => $student['open_id'],
            'package_id' => $packageId, // 产品包id
            'channel' => $channel,
            'sale_shop' => ErpPackageV1Model::SALE_SHOP_AI_PLAY,
            'pay_type' => $payType, // 支付类型 1 直付 2 账户
            'pay_channel' => $payChannel, // 三方支付渠道
            'success_url' => $callbacks['success_url'] ?? null, // 支付宝web 支付成功跳转链接
            'cancel_url' => $callbacks['cancel_url'] ?? null, // 支付宝web 支付失败跳转链接
            'result_url' => $callbacks['result_url'] ?? null, // 微信H5 支付结果跳转链接
            'employee_uuid' => $employeeUuid, // 成单人
        ]);
        if (empty($result)) {
            return Valid::addAppErrors([], 'create_bill_error');
        }
        return $result;
    }

    public static function callbacks($channel)
    {
        $successUrl = null;
        $resultUrl = null;
        if ($channel == ErpPackageV1Model::CHANNEL_WX) {
            list($successUrl, $resultUrl) = DictConstants::get(DictConstants::WEIXIN_STUDENT_CONFIG, ['success_url_v1', 'result_url_v1']);
        }
        return [
            'success_url' => $successUrl,
            'result_url' => $resultUrl
        ];
    }
}