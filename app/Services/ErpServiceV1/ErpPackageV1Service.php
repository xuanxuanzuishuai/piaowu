<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/9/7
 * Time: 2:37 PM
 */

namespace App\Services\ErpServiceV1;



use App\Models\CategoryV1Model;
use App\Models\GiftCodeModel;
use App\Models\ModelV1\ErpPackageGoodsV1Model;
use App\Models\ModelV1\ErpPackageV1Model;
use App\Models\PackageExtModel;

class ErpPackageV1Service
{

    /**
     * 是否体验时长
     * @param $packageId
     * @return bool
     */
    public static function isTrailPackage($packageId)
    {
        $goods = ErpPackageGoodsV1Model::goodsListByPackageId($packageId);

        $types = array_column($goods, 'category_sub_type');
        if (in_array(CategoryV1Model::DURATION_TYPE_TRAIL, $types)) {
            return true;
        }
        return false;
    }

    /**
     * 获取新产品包id，name
     * @param $subType
     * @return array|null
     */
    public static function getPackages($subType)
    {
        return ErpPackageV1Model::getPackagesByType($subType);
    }


    /**
     * 用户首次购买年卡时间
     * @param $studentIds
     * @return array
     */
    public static function getNormalFirstPaidTime($studentIds)
    {
        // 旧产品包--年卡
        $packages = PackageExtModel::getPackages(['package_type' => PackageExtModel::PACKAGE_TYPE_NORMAL]);
        $packageIds = array_column($packages, 'package_id');
        $olds = GiftCodeModel::getOldPaidNormal($studentIds, $packageIds);


        // 新产品包---正式时长
        $newIds = ErpPackageV1Model::getNormalPackageIds();
        $news = GiftCodeModel::getNewPaidNormal($studentIds, $newIds);

        $codes = array_merge($olds, $news);

        $data = [];
        foreach ($codes as $value) {
            $studentId = $value['buyer'];
            if (empty($data[$studentId]) || (!empty($data[$studentId]) && ($data[$studentId] > $value['buy_time']))) {
                $data[$studentId] = $value['buy_time'];
            }
        }
        return $data;
    }

}