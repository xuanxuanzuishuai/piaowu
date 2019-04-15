<?php
/**
 * Created by PhpStorm.
 * User: lijie
 * Date: 2018/10/30
 * Time: 10:09 AM
 */
namespace App\Services;

use App\Models\AppModel;

class AppService
{
    /**
     * 获取所有正常记录
     * @return array
     */
    public static function getNormalRecords()
    {
        $result = AppModel::getRecordsApp();
        return $result;
    }

    /**
     * 判断应用名称是否存在
     * @param $app_id
     * @return bool
     */
    public static function isExits($app_id)
    {
        $is_exits = false;
        $result = AppModel::getById($app_id);
        if ($result){
            $is_exits = true;
        }
        return $is_exits;
    }

    /**
     * 获取应用类型
     * @param $type
     * @return array
     */
    public static function getAppTypeList($type)
    {
        return AppModel::getAppTypeHandle($type);
    }

    /**
     * 获取APP格式化数据
     * @return array
     */
    public static function getAppMap()
    {
        $data = AppModel::getRecordsApp();
        $res = [];
        foreach($data as $item){
            $res[$item['id']] = $item['name'];
        }
        return $res;
    }
}