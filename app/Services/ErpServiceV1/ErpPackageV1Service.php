<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/9/7
 * Time: 2:37 PM
 */

namespace App\Services\ErpServiceV1;



use App\Models\CategoryV1Model;
use App\Models\ModelV1\ErpPackageGoodsV1Model;

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
}