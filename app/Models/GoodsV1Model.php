<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/10/26
 * Time: 3:56 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;

class GoodsV1Model extends Model
{
    public static $table = "erp_goods_v1";
    //赠送奖章
    const FREE_MEDAL_CATEGORY_ID = 17;
    /**
     * @return array|null
     * 类型为奖章的所有商品信息
     */
    public static function getMedalInfo()
    {
        $sql = "select g.id medal_id,
g.extension->>'$.parent_id' category_id, 
p.`name` medal_category_name, 
p.`desc`->>'$.desc' detail_desc,
g.thumbs,
g.extension->>'$.level' medal_level,
c.type,
p.extension->>'$.medal_type' medal_type,
g.name medal_name
from " . self::$table . " g inner join " . CategoryV1Model::$table . " c
on g.category_id = c.id
and c.type = " . CategoryV1Model::MEDAL_AWARD_TYPE . "
inner join " . self::$table . " p on g.extension->>'$.parent_id' = p.id";
        return MysqlDB::getDB()->queryAll($sql);
    }

    /**
     * @param $hasGetMedalCategory
     * @return array|null
     * 尚未获得的奖章类型
     */
    public static function getNotGetMedalCategory($hasGetMedalCategory)
    {
        $sql = "SELECT * FROM " . self::$table . " WHERE `id` 
        NOT IN (" . implode(',', $hasGetMedalCategory) . ") AND `category_id` = " . self::FREE_MEDAL_CATEGORY_ID .
            " AND extension->>'$.parent_id' = 0";
        return MysqlDB::getDB()->queryAll($sql);
    }
}