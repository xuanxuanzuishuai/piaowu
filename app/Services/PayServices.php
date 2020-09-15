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
use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Models\GiftCodeModel;
use App\Models\ModelV1\ErpPackageV1Model;
use App\Models\PackageExtModel;
use App\Models\ReviewCourseModel;
use App\Models\StudentModel;
use App\Models\StudentModelForApp;
use App\Services\ErpServiceV1\ErpPackageV1Service;

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
     * @param $platform
     * @return array
     */
    public static function getAppPackageData($studentId, $platform)
    {
        $student = StudentModelForApp::getById($studentId);

        $excludeTypes = [];
        if ($student['has_review_course'] != ReviewCourseModel::REVIEW_COURSE_NO) {
            $excludeTypes []= PackageExtModel::PACKAGE_TYPE_TRIAL;
        }

        // ios android 使用不同的产品包
        list($trialPackageIos, $trialPackageAndroid) = DictConstants::getValues(
            DictConstants::APP_CONFIG_STUDENT,
            ['trial_package_ios', 'trial_package_android']
        );
        $excludeIds = [];
        if ($platform == 'ios') {
            $excludeIds []= $trialPackageAndroid;
        } else {
            $excludeIds []= $trialPackageIos;
        }
        $packages = PackageService::getAppPackages($excludeTypes, $excludeIds);

        $freePackage = null;
        $withFreePackage = StudentServiceForApp::canTrial($student);
        if ($withFreePackage) {
            $freePackageData = DictConstants::get(DictConstants::APP_CONFIG_STUDENT, 'free_package');
            $freePackage = json_decode($freePackageData, true);
            $freePackage['type'] = 'free';
        }

        $data = [
            'packages' => $packages,
            'free_package' => $freePackage,
        ];

        return $data;
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
     * @param $packageId
     * @param $uuid
     * @param $payChannel
     * @param $clientIp
     * @param array $params
     * @return array
     */
    public static function webCreateBill($packageId, $uuid, $payChannel, $clientIp, $params = [])
    {
        $erp = new Erp();

        // 报课渠道 1 APP 2 公众号 3 ERP
        $package = $erp->getPackageDetail($packageId, $uuid, 2);
        if (empty($package)) {
            return [];
        }

        // erp的packages返回的是元为单位，需转为分
        $amount = $package['sprice'] * 100;

        list($successUrl, $cancelUrl, $resultUrl) = DictConstants::get(
            DictConstants::WEB_STUDENT_CONFIG,
            ['success_url', 'cancel_url', 'result_url']
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
            $amount,
            $package['oprice'] * 100,
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
     * @param $employeeUuid
     * @return array|bool
     */
    public static function weixinCreateBill($studentId, $packageId, $payChannel, $clientIp, $studentAddressId, $openId, $employeeUuid)
    {
        $student = StudentModelForApp::getById($studentId);
        $uuid = $student['uuid'];

        $erp = new Erp();
        // 报课渠道 1 APP 2 公众号 3 ERP
        $package = $erp->getPackageDetail($packageId, $uuid, 2);
        if (empty($package)) {
            return false;
        }

        // erp的packages返回的是元为单位，需转为分
        $amount = $package['sprice'] * 100;

        $testStudents = DictConstants::get(DictConstants::APP_CONFIG_STUDENT, 'pay_test_students');
        // 测试支付用户
        $testStudentUuids = explode(',', $testStudents);
        if (in_array($uuid, $testStudentUuids)) {
            $amount = 1;
        }

        list($successUrl, $resultUrl) = DictConstants::get(DictConstants::WEIXIN_STUDENT_CONFIG, ['success_url', 'result_url']);
        $ret = $erp->createBill(
            $uuid,
            $packageId,
            $payChannel,
            $clientIp,
            $amount,
            $package['oprice'] * 100,
            [
                'success_url' => $successUrl,
                'result_url' => $resultUrl,
            ],
            [
                'student_address_id' => $studentAddressId,
                'open_id' => $openId,
                'employee_uuid' => $employeeUuid
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


    /**
     * 用户是否购买过体验包
     * @param $studentId
     * @return bool
     */
    public static function hasTrialed($studentId)
    {
        $trailPackages = PackageExtModel::getPackages(['package_type' => PackageExtModel::PACKAGE_TYPE_TRIAL]);
        $trialPackageIds = array_column($trailPackages, 'package_id');
        if (empty($trialPackageIds)) {
            return false;
        }

        // 新产品包---体验时长
        $newTrailIds = ErpPackageV1Model::getTrailPackageIds();
        $trialPackageIds = array_merge($trialPackageIds, $newTrailIds);

        $trialCode = GiftCodeModel::getRecords([
            'buyer' => $studentId,
            'bill_package_id' => $trialPackageIds
        ], 'id', false);

        return count($trialCode) > 0;
    }

    public static function trialedUserByMobile($mobile)
    {
        $trailPackages = PackageExtModel::getPackages(['package_type' => PackageExtModel::PACKAGE_TYPE_TRIAL]);
        $trialPackageIds = array_column($trailPackages, 'package_id');
        if (empty($trialPackageIds)) {
            return false;
        }
        if(!is_array($mobile)) {
            $mobile = [$mobile];
        }

        $in = Util::buildSqlIn($mobile);

        $pin = Util::buildSqlIn($trialPackageIds);

        $s = StudentModel::$table;
        $g = GiftCodeModel::$table;

        $db = MysqlDB::getDB();

        return $db->queryAll("select s.mobile from {$g} g inner join {$s} s on s.id = g.buyer 
                    where s.mobile in ({$in}) and g.bill_package_id in ({$pin}) group by s.mobile");
    }

    /**
     * 是否是体验课包
     * @param $packageId
     * @return bool
     */
    public static function isTrialPackage($packageId)
    {
        if (empty($packageId)) {
            return false;
        }

        $trailPackages = PackageExtModel::getPackages([
            'package_type' => PackageExtModel::PACKAGE_TYPE_TRIAL,
            'package_id' => $packageId
        ]);

        return empty($trailPackages) ? false : true;
    }

    /**
     * 积分商城
     * @param $channel
     * @param $page
     * @param $count
     * @return array|bool
     */
    public static function getPackageV1List($channel, $page, $count)
    {
        // sale_shop 销售商城分类 1 音符商城 2 金叶子商城
        // channel 渠道授权 1 Android 2 IOS 4 公众号 8 ERP
        $params = [
            'sale_shop' => ErpPackageV1Model::SALE_SHOP_NOTE,
            'channel' => $channel,
            'page' => $page,
            'count' => $count
        ];

        $erp = new Erp();
        $packages = $erp->packageV1List($params);
        return $packages;
    }

    /**
     * 新产品详情
     * @param $packageId
     * @param $channel
     * @param $uuid
     * @param $saleShop
     * @return array|bool
     */
    public static function getPackageV1Detail($packageId, $channel, $uuid, $saleShop = ErpPackageV1Model::SALE_SHOP_NOTE)
    {
        // sale_shop 销售商城分类 1 音符商城 2 金叶子商城
        // channel 渠道授权 1 Android 2 IOS 4 公众号 8 ERP
        $params = [
            'sale_shop' => $saleShop,
            'channel' => $channel,
            'package_id' => $packageId,
            'uuid' => $uuid,
        ];
        $erp = new Erp();
        $detail = $erp->packageV1Detail($params);
        return $detail;
    }
}
