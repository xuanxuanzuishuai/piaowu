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

class AreaService
{
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
        if (!empty($params['province_code'])) {
            $where['province_adcode'] = $params['province_code'];
        }
        if (!empty($params['province_name'])) {
            $where['province_name[~]'] = $params['province_name'];
        }
        return AreaProvinceModel::getRecords($where, ['id', 'province_adcode(province_code)', 'province_name']);
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
        if (!empty($params['city_code'])) {
            $where['city_adcode'] = $params['city_code'];
        }
        if (!empty($params['city_name'])) {
            $where['city_name[~]'] = $params['city_name'];
        }
        if (!empty($params['province_id'])) {
            $where['province_id'] = $params['province_id'];
        }
        if (!empty($params['province_code'])) {
            $where['province_adcode'] = $params['province_code'];
        }
        return AreaCityModel::getRecords($where, ['id', 'city_adcode(city_code)', 'city_name']);
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
        if (!empty($params['district_code'])) {
            $where['district_adcode'] = $params['district_code'];
        }
        if (!empty($params['district_name'])) {
            $where['district_name[~]'] = $params['district_name'];
        }
        if (!empty($params['city_id'])) {
            $where['city_id'] = $params['city_id'];
        }
        if (!empty($params['city_code'])) {
            $where['city_adcode'] = $params['city_code'];
        }
        if (!empty($params['province_id'])) {
            $where['province_id'] = $params['province_id'];
        }
        if (!empty($params['province_code'])) {
            $where['province_adcode'] = $params['province_code'];
        }
        return AreaDistrictModel::getRecords($where, ['id', 'district_adcode(district_code)', 'district_name']);
    }
}