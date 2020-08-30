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

    /**
     * @return array|null
     * 类型为奖章的所有商品信息
     */
    public static function getMedalInfo()
    {
        $sql = "select g.id medal_id,
c.id category_id, 
c.`name` medal_category_name, 
c.`desc`->>'$.desc' detail_desc,
g.thumbs,
g.extension->>'$.level' medal_level,
c.type
from ". self::$table ." g inner join " . CategoryV1Model::$table . " c
on g.category_id = c.id
and c.type = " . CategoryV1Model::MEDAL_AWARD_TYPE;
        return MysqlDB::getDB()->queryAll($sql);
    }
}