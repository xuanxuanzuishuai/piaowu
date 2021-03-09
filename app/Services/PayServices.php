<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/2/3
 * Time: 13:55
 */

namespace App\Services;


use App\Libs\Erp;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;

class PayServices
{
    const PACKAGE_4900 = 1; // 49元课包
    const PACKAGE_990  = 2; // 9.9元课包
    const PACKAGE_1    = 3; // 0.01元课包
    const PACKAGE_1000 = 4; // 1元课包
    const PACKAGE_4900_v2 = 5; // 49元课包（两周体验营+礼盒）

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
}
