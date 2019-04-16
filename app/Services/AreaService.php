<?php
/**
 * Created by PhpStorm.
 * User: lijie
 * Date: 2018/10/26
 * Time: 11:56 AM
 */

namespace App\Services;

use App\Models\AreaModel;

class AreaService
{
    /**
     * 根据 parent_code 获取区域信息
     * @param $parent_code
     * @return mixed
     */
    public static function getAreaByParentCode($parent_code)
    {
        //判断传入的值是否为空，如果为空，则取顶级区域
        $parent_code = empty($parent_code) ? '000000' : $parent_code;

        $result = AreaModel::getRecordsByParentCode($parent_code);
        return $result;
    }

    /**
     * 根据code获取信息
     * @param $code
     * @return array
     */
    public static function getByCode($code)
    {
        $result = AreaModel::getRecordByCode($code);
        return $result;
    }
}