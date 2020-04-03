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
use App\Models\ReviewCourseModel;
use App\Models\StudentModelForApp;

class PayServices
{
    // 订单支付状态
    const BILL_STATUS_SUCCESS = '1';
    const BILL_STATUS_PROCESSING = '0';
    const BILL_STATUS_FAILED = '-1';

    const PAY_CHANNEL_PUB = 21; //微信公众号内支付

    /**
     * 获取产品包
     * @param int $studentId
     * @return array
     */
    public static function getPackages($studentId)
    {
        $packages = [];

        $student = StudentModelForApp::getById($studentId);
        $withFreePackage = StudentServiceForApp::canTrial($student);
        if ($withFreePackage) {
            $freePackage = DictConstants::get(DictConstants::APP_CONFIG_STUDENT, 'free_package');
            $packages[] = json_decode($freePackage, true);
        }


        $erp = new Erp();
        $ret = $erp->getPackages($student['uuid'], 1);
        $erpPackages = $ret['data'] ?? [];

        usort($erpPackages, function ($a, $b) {
            if ($a['oprice'] == $b['oprice']) {
                return $a['package_id'] > $b['package_id'];
            }
            return $a['oprice'] > $b['oprice'];
        });

        $package49 = DictConstants::get(DictConstants::WEB_STUDENT_CONFIG, 'package_id');

        foreach ($erpPackages as $pkg) {
            // 购买过点评课的学生不能够买点评课体验包(49)
            if ($pkg['package_id'] == $package49 && $student['has_review_course'] != ReviewCourseModel::REVIEW_COURSE_NO) {
                continue;
            }

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

    /**
     * 获取课包详情
     * @param $packageId
     * @param $studentId
     * @return array
     */
    public static function getPackageDetail($packageId, $studentId)
    {
        $student = StudentModelForApp::getById($studentId);
        $package49 = DictConstants::get(DictConstants::WEB_STUDENT_CONFIG, 'package_id');
        // 购买过点评课的学生不能够买点评课体验包(49)
        if ($packageId == $package49 && $student['has_review_course'] != ReviewCourseModel::REVIEW_COURSE_NO) {
            return [];
        }

        // 报课渠道 1 APP 2 公众号 3 ERP
        $erp = new Erp();
        $package = $erp->getPackageDetail($packageId, $student['uuid'], 2);
        if (empty($package)) {
            return [];
        }
        $package['sprice'] = $package['sprice'] . '元';
        $package['oprice'] = $package['oprice'] . '元';
        return $package;
    }

    public static function createBill($uuid, $packageId, $payChannel, $clientIp)
    {
        $erp = new Erp();

        $erpPackages = $erp->getPackages($uuid, 1);
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

        if (empty($ret)) {
            return false;
        }

        $bill = $ret['data']['bill'];
        // 写入ai_bill，自动激活
        $autoApply = 1;
        AIBillService::addAiBill($bill['id'], $uuid, $autoApply);
        return $ret;
    }

    public static function getBillStatus($billId)
    {
        $erp = new Erp();

        $bill = $erp->getBill($billId);
        if (empty($bill)) {
            return null;
        }

        return $bill['pay_status'];
    }

    /**
     * landing页21天点评课购买
     * @param $uuid
     * @param $payChannel
     * @param $clientIp
     * @param $params
     * @return array
     */
    public static function webCreateBill($uuid, $payChannel, $clientIp, $params = [])
    {
        $erp = new Erp();

        list($successUrl, $cancelUrl, $resultUrl, $packageId) = DictConstants::get(
            DictConstants::WEB_STUDENT_CONFIG,
            ['success_url', 'cancel_url', 'result_url', 'package_id']
        );

        /*
         * TODO: 域名替换
         * 新域名 referral.xiaoyezi.com 和 老域名 smartpeilian.xiaoyezi.com 同时存在
         * 回调地址目前配置的还是老域名
         * 如果从新域名进入，需要把回调地址的老域名替换成新域名
         * smartpeilian -> referral
         */
        $origin = $_SERVER['HTTP_ORIGIN'];
        $transUrl = (strpos($origin, 'referral') !== false);

        $ret = $erp->createBill(
            $uuid,
            $packageId,
            $payChannel,
            $clientIp,
            1,
            1,
            [
                'success_url' => $transUrl ? str_replace('smartpeilian', 'referral', $successUrl) : $successUrl,
                'cancel_url' => $transUrl ? str_replace('smartpeilian', 'referral', $cancelUrl) : $cancelUrl,
                'result_url' => $transUrl ? str_replace('smartpeilian', 'referral', $resultUrl) : $resultUrl,
            ],
            $params
        );
        if (empty($ret)) {
            return [];
        }

        $bill = $ret['data']['bill'];
        $autoApply = 1;
        // 写入ai_bill，自动激活
        AIBillService::addAiBill($bill['id'], $uuid, $autoApply);

        return $ret;
    }

    /**
     * 微信创建订单
     * @param $studentId
     * @param $packageId
     * @param $payChannel
     * @param $clientIp
     * @param $studentAddressId
     * @param $openId
     * @return array|bool
     */
    public static function weixinCreateBill($studentId, $packageId, $payChannel, $clientIp, $studentAddressId, $openId)
    {
        $student = StudentModelForApp::getById($studentId);
        $uuid = $student['uuid'];

        $erp = new Erp();
        // 报课渠道 1 APP 2 公众号 3 ERP
        $package = $erp->getPackageDetail($packageId, $uuid, 2);
        if (empty($package)) {
            return false;
        }

        list($testStudents, $successUrl, $cancelUrl, $resultUrl) = DictConstants::get(
            DictConstants::WEIXIN_STUDENT_CONFIG,
            ['success_url', 'cancel_url', 'result_url']
        );

        // erp的packages返回的是元为单位，需转为分
        $amount = $package['sprice'] * 100;
        // 测试支付用户
        $testStudentUuids = explode(',', $testStudents);
        if (in_array($uuid, $testStudentUuids)) {
            $amount = 1;
        }

        $ret = $erp->createBill(
            $uuid,
            $packageId,
            $payChannel,
            $clientIp,
            $amount,
            $package['oprice'] * 100,
            [
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'result_url' => $resultUrl,
            ],
            [
                'student_address_id' => $studentAddressId,
                'open_id' => $openId
            ]
        );
        if (empty($ret)) {
            return false;
        }

        $bill = $ret['data']['bill'];
        $autoApply = 0;
        // 写入ai_bill，手动激活
        AIBillService::addAiBill($bill['id'], $uuid, $autoApply);
        return $ret;
    }
}
