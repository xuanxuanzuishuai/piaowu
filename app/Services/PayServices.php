<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/8/1
 * Time: 1:55 PM
 */

namespace App\Services;

use App\Libs\DictConstants;
use App\Libs\Erp;

class PayServices
{
    public static function getPackages()
    {
        $freePackage = DictConstants::get(DictConstants::APP_CONFIG_STUDENT, 'free_package');

        $packages[] = json_decode($freePackage, true);

        $erp = new Erp();
        $ret = $erp->getPackages();
        $erpPackages = $ret['data'] ?? [];

        usort($erpPackages, function ($a, $b) {
            if ($a['oprice'] == $b['oprice']) {
                return $a['package_id'] < $b['package_id'];
            }
            return $a['oprice'] < $b['oprice'];
        });

        foreach ($erpPackages as $pkg) {
            $packages[] = [
                'package_id' => $pkg['package_id'],
                'package_name' => $pkg['package_name'],
                'price' => $pkg['sprice'] . '元',
                'origin_price' => $pkg['oprice'] . '元',
                'start_time' => $pkg['start_time'],
                'end_time' => $pkg['end_time'],
            ];
        }

        return $packages;
    }

    public static function createBill($uuid, $packageId, $payChannel, $clientIp)
    {
        $erp = new Erp();

        $erpPackages = $erp->getPackages();
        if (empty($erpPackages['data'])) {
            return false;
        }

        // 检查package并获取价格
        $packages = $erpPackages['data'];
        $price = null;
        $originPrice = null;
        foreach ($packages as $pkg) {
            if ($pkg['package_id'] == $packageId) {
                // erp的packages返回的是元为单位，需转为分
                $price = $pkg['sprice'] * 100;
                $originPrice = $pkg['oprice'] * 100;
                break;
            }
        }

        if ($price === null) {
            return false;
        }

        list($testStudents, $successUrl, $cancelUrl, $resultUrl) = DictConstants::get(
            DictConstants::APP_CONFIG_STUDENT,
            ['pay_test_students', 'success_url', 'cancel_url', 'result_url']
        );

        // 测试支付用户
        $testStudentUuids = explode(',', $testStudents);
        if (in_array($uuid, $testStudentUuids)) {
            $price = 1;
        }

        $ret = $erp->createBill(
            $uuid,
            $packageId,
            $payChannel,
            $clientIp,
            $price,
            $originPrice,
            [
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'result_url' => $resultUrl,
            ]
        );

        return $ret;
    }
}
