<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/2/3
 * Time: 13:55
 */

namespace App\Services;

use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Models\Dss\DssCategoryV1Model;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Erp\ErpPackageGoodsV1Model;

class PayServices
{
    const PACKAGE_4900 = 1; // 49元课包
    const PACKAGE_990  = 2; // 9.9元课包
    const PACKAGE_1    = 3; // 0.01元课包
    const PACKAGE_0    = 6; // 0元支付0.01元课包
    const PACKAGE_1000 = 4; // 1元课包
    const PACKAGE_4900_v2 = 5; // 49元课包（两周体验营+礼盒）
    const PACKAGE_490    = 9; //4.9元课包

    const PAY_CHANNEL_ALIPAY         = 1;  // 支付宝
    const PAY_CHANNEL_WEIXIN_H5      = 2;  // 微信 H5
    const PAY_CHANNEL_ALIPAY_PC      = 12; // 支付宝 PC
    const PAY_CHANNEL_PUB            = 21; // 微信公众号内支付
    const PAY_CHANNEL_WEIXIN_MINIPRO = 23; // 微信小程序

    const PAY_CHANNEL_V1_ALIPAY         = 4000;
    const PAY_CHANNEL_V1_WEIXIN         = 4001;
    const PAY_CHANNEL_V1_WEIXIN_H5      = 4006;
    const PAY_CHANNEL_V1_ALIPAY_PC      = 4019;
    const PAY_CHANNEL_V1_WEIXIN_MINIPRO = 4020;

    const PAY_TYPE_DIRECT = 1; // 直付
    const PAY_TYPE_ACCOUNT = 2; // 账户

    const NEW_PAY_CHANNEL_DICT = [
        self::PAY_CHANNEL_ALIPAY         => self::PAY_CHANNEL_V1_ALIPAY,
        self::PAY_CHANNEL_WEIXIN_H5      => self::PAY_CHANNEL_V1_WEIXIN_H5,
        self::PAY_CHANNEL_ALIPAY_PC      => self::PAY_CHANNEL_V1_ALIPAY_PC,
        self::PAY_CHANNEL_PUB            => self::PAY_CHANNEL_V1_WEIXIN,
        self::PAY_CHANNEL_WEIXIN_MINIPRO => self::PAY_CHANNEL_V1_WEIXIN_MINIPRO,
    ];
    /**
     * 获取学生体验课包的订单
     * @param $mobile
     * @return array
     */
    public static function trialedUserByMobile($mobile)
    {
        if (!is_array($mobile)) {
            $mobile = [$mobile];
        }
        $mobiles = Util::buildSqlIn($mobile);
        //获取旧产品包订单:体验包
        $trailPackages = DssPackageExtModel::getPackages(['package_type' => DssPackageExtModel::PACKAGE_TYPE_TRIAL]);
        $trialPackageIds = array_column($trailPackages, 'package_id');
        $oldTrials = [];
        if (!empty($trialPackageIds)) {
            $oldPackageIds = Util::buildSqlIn($trialPackageIds);
            $oldTrials = DssGiftCodeModel::getStudentTrailOrderList($mobiles, $oldPackageIds, DssGiftCodeModel::PACKAGE_V1_NOT);
        }
        //获取新产品包订单:体验时长
        $newTrailIds = DssErpPackageV1Model::getTrailPackageIds();
        $newTrials = [];
        if (!empty($newTrailIds)) {
            $newPackageIds = Util::buildSqlIn($newTrailIds);
            $newTrials = DssGiftCodeModel::getStudentTrailOrderList($mobiles, $newPackageIds, DssGiftCodeModel::PACKAGE_V1);
        }
        return array_merge($oldTrials, $newTrials);
    }

    /**
     * 新老支付渠道转换
     * @param $oldPayChannel
     * @return int
     */
    public static function payChannelToV1($oldPayChannel)
    {
        return self::NEW_PAY_CHANNEL_DICT[$oldPayChannel] ?? $oldPayChannel;
    }

    /**
     * 订单状态查询
     * @param $orderId
     * @return string|null
     */
    public static function getBillStatus($orderId)
    {
        $erp = new Erp();

        $params = ['order_id' => $orderId];
        $resp = $erp->billStatusV1($params);

        if (empty($resp) || $resp['code'] != HttpHelper::STATUS_SUCCESS) {
            return null;
        }

        return (string)$resp['data']['order_status'];
    }

    /**
     * 下单时根据pkg参数查询对应Package ID
     * @param $pkg
     * @return array|mixed|null
     */
    public static function getPackageIDByParameterPkg($pkg)
    {
        $arr = [
            self::PACKAGE_1       => DictConstants::get(DictConstants::WEB_STUDENT_CONFIG, 'mini_001_package_id'), //0.01
            self::PACKAGE_0       => DictConstants::get(DictConstants::WEB_STUDENT_CONFIG, 'mini_0_package_id'), //0
            self::PACKAGE_990     => DictConstants::get(DictConstants::WEB_STUDENT_CONFIG, 'mini_package_id_v1'), // 9.9
            self::PACKAGE_1000    => DictConstants::get(DictConstants::WEB_STUDENT_CONFIG, 'mini_1_package_id'), //1
            self::PACKAGE_4900    => DictConstants::get(DictConstants::WEB_STUDENT_CONFIG, 'package_id'), //49
            self::PACKAGE_4900_v2 => DictConstants::get(DictConstants::WEB_STUDENT_CONFIG, 'package_id_v2'), //49 v2
            self::PACKAGE_490     => DictConstants::get(DictConstants::WEB_STUDENT_CONFIG, 'package_id_4_9')
        ];
        return $arr[$pkg] ?? DictConstants::get(DictConstants::WEB_STUDENT_CONFIG, 'mini_package_id_v1');
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

        // 新产品包
        $goods = ErpPackageGoodsV1Model::goodsListByPackageId($packageId);
        $types = array_column($goods, 'category_sub_type');
        if (in_array(DssCategoryV1Model::DURATION_TYPE_TRAIL, $types)) {
            return true;
        }

        // 旧产品包
        $trailPackages = DssPackageExtModel::getPackages([
            'package_type' => DssPackageExtModel::PACKAGE_TYPE_TRIAL,
            'package_id' => $packageId
        ]);

        if (!empty($trailPackages)) {
            return true;
        }

        return false;
    }
}
