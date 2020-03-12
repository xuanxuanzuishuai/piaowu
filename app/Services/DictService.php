<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/11
 * Time: 12:08 PM
 */

namespace App\Services;

use App\Models\DictModel;

class DictService
{
    /**
     * 获取指定类型数据map
     * @param $type
     * @return array
     */
    public static function getTypeMap($type)
    {
        $records = DictModel::getList($type);
        $data = [];
        foreach ($records as $record) {
            $data[$record['key_code']] = $record['key_value'];
        }
        return $data;
    }

    /**
     * 根据类型获取列表
     * @param $type
     * @return mixed
     */
    public static function getList($type){
        return DictModel::getList($type);
    }

    /**
     * 获取多个list
     * @param $types
     * @return mixed
     */
    public static function getListsByTypes($types){
        return DictModel::getListsByTypes($types);
    }

    /**
     * 获取显示值
     * @param $type
     * @param $keyCode
     * @return mixed
     */
    public static function getKeyValue($type, $keyCode){
        return DictModel::getKeyValue($type, $keyCode);
    }

    /**
     * 获取多个Key值
     * @param $type
     * @param $keyCodes
     * @return array
     */
    public static function getKeyValuesByArray($type, $keyCodes){
        return DictModel::getKeyValuesByArray($type, $keyCodes);
    }


    /**
     * 添加字典值
     * @param $type
     * @param $keyCode
     * @param $keyValue
     * @param string $typeName
     * @param string $desc
     * @return mixed
     */
    public static function addKeyValue($type, $keyCode, $keyValue, $typeName = '', $desc = ''){
        return DictModel::addKeyValue($type, $keyCode, $keyValue, $typeName, $desc);
    }

    /**
     * 删除字典值
     * @param $type
     * @param $keyCode
     * @return mixed
     */
    public static function delete($type, $keyCode){
        return DictModel::delete($type, $keyCode);
    }

    /**
     * 更新字典值
     * @param $type
     * @param $keyCode
     * @param $keyValue
     * @return int|null
     */
    public static function updateValue($type, $keyCode, $keyValue)
    {
        return DictModel::updateValue($type, $keyCode, $keyValue);
    }
}