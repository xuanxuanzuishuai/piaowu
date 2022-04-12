<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/01/26
 * Time: 5:14 PM
 */

namespace App\Models;

class UniqueIdGeneratorModel extends Model
{
    //表名称
    public static $table = "unique_id_generator";
    //业务类型
    const BUSINESS_TYPE_DELIVER = 1;//实物发货单


    /**
     * 获取指定业务类型的当前最新配置数据
     * @param $businessType
     * @return array
     */
    public static function getGeneratorConfig($businessType): array
    {
        $data = self::getRecord(['business_type' => $businessType], ['id', 'max_id', 'step', 'version']);
        return empty($data) ? [] : $data;
    }

    /**
     * 根据用户id更新用户微信昵称和头像地址
     * @param $id
     * @param $maxId
     * @param $version
     * @return bool
     */
    public static function updateGeneratorConfig($id, $maxId, $version): bool
    {
        $res = self::updateRecord($id, ['max_id' => $maxId, 'version' => $version]);
        return !empty($res);
    }
}