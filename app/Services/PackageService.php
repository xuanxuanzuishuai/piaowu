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
use App\Models\RoleModel;

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
}