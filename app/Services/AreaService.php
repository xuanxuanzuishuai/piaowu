<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/6/26
 * Time: 上午11:34
 */

namespace App\Services;

use App\Models\AreaCityModel;
use App\Models\AreaDistrictModel;
use App\Models\AreaProvinceModel;
use App\Models\CountryCodeModel;

class AreaService
{
    /**
     * 获取国家列表
     * @param $params
     * @return array
     */
    public static function countryList($params)
    {
        $where = ['id[>]' => 0];
        if (!empty($params['country_code'])) {
            $where['country_code'] = $params['country_code'];
        }
        if (!empty($params['country_name'])) {
            $where['name[~]'] = $params['country_name'];
        }
        return CountryCodeModel::getRecords($where, ['country_code', 'name']);
    }

    /**
     * 获取省列表
     * @param $params
     * @return array
     */
    public static function provinceList($params)
    {
        $where = ['id[>]' => 0];
        if (!empty($params['id'])) {
            $where['id'] = $params['id'];
        }
        if (!empty($params['province_name'])) {
            $where['province_name[~]'] = $params['province_name'];
        }
        return AreaProvinceModel::getRecords($where, ['id', 'province_name']);
    }

    /**
     * 获取市列表
     * @param $params
     * @return array
     */
    public static function cityList($params)
    {
        $where = ['id[>]' => 0];
        if (!empty($params['id'])) {
            $where['id'] = $params['id'];
        }
        if (!empty($params['city_name'])) {
            $where['city_name[~]'] = $params['city_name'];
        }
        return AreaCityModel::getRecords($where, ['id', 'city_name']);
    }

    /**
     * 获取区/县列表
     * @param $params
     * @return array
     */
    public static function districtList($params)
    {
        $where = ['id[>]' => 0];
        if (!empty($params['id'])) {
            $where['id'] = $params['id'];
        }
        if (!empty($params['district_name'])) {
            $where['district_name[~]'] = $params['district_name'];
        }
        return AreaDistrictModel::getRecords($where, ['id', 'district_name']);
    }
}