<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/2/1
 * Time: 11:11
 */

namespace App\Services;

use App\Libs\Constants;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Erp\ErpGiftGroupV1Model;
use App\Models\Erp\ErpPackageGoodsV1Model;
use App\Models\Erp\ErpPackageV1Model;
use App\Libs\Exceptions\RunTimeException;
use App\Models\Dss\DssCategoryV1Model;
use App\Libs\DictConstants;
use App\Libs\AliOSS;

class PackageService
{
    /**
     * 课包搜索
     * @param $params
     * @return array|mixed
     */
    public static function packageSearch($params)
    {
        if (!empty($params['name'])) {
            $where['name[~]'] = $params['name'];
        }
        if (empty($where)) {
            return [];
        }

        return DssErpPackageV1Model::getRecords($where, ['id', 'name']);
    }

    /**
     * 新产品包通过subtype查询
     * @param $subType
     * @return array|mixed
     */
    public static function getPackageBySubType($subType)
    {
        $packageId = DssErpPackageV1Model::getPackageIds($subType);
        if (empty($packageId)) {
            return [];
        }
        return DssErpPackageV1Model::getRecords(['id' => $packageId], ['id', 'name']);
    }

    /**
     * 新产品包详情
     * @param $packageId
     * @return array|bool
     * @throws RunTimeException
     */
    public static function getPackageV1Detail($packageId)
    {
        $package = ErpPackageV1Model::packageDetail($packageId);
        if (empty($package)) {
            throw new RunTimeException(['package_not_available']);
        }

        $goods = ErpPackageGoodsV1Model::goodsListByPackageId($packageId, ErpPackageGoodsV1Model::SUCCESS_NORMAL);

        $package['need_address'] = DssCategoryV1Model::containObject($goods);
        $package['goods'] = ErpPackageGoodsV1Service::formatGoods($goods);
        $package['flags'] = json_decode($package['flags'], 1);
        $package['stock'] = $package['stock'] ?? '0';

        // 介绍图、详情图
        $thumbs = json_decode($package['thumbs'], 1);
        $package['thumbs'] = array_map(function ($url) {
            return AliOSS::replaceShopCdnDomain($url);
        }, $thumbs['thumbs']);

        $details = [];
        if (!empty($thumbs['details']) && is_array($thumbs['details'])) {
            $details = array_map(function ($url) {
                return AliOSS::replaceShopCdnDomain($url);
            }, $thumbs['details']);
        }
        $package['details'] = $details;
        if ($package['status'] != ErpPackageV1Model::STATUS_ON_SALE) {
            $package['status'] = ErpPackageV1Model::STATUS_OFF_SALE;
        }

        return $package;
    }
}