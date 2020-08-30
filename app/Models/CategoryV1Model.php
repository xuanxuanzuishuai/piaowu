<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/7/6
 * Time: 下午3:01
 */

namespace App\Models;
use App\Libs\MysqlDB;
use App\Models\Model;

class CategoryV1Model extends Model
{
    //奖励类型为奖章
    const MEDAL_AWARD_TYPE = 4;
    public static $table = 'erp_category_v1';

    /**
     * @return array|null
     * 所有奖章类别的基础信息
     */
    public static function getMedalCategoryRelateInfo()
    {
        $sql = "select 
c.`name` category_name,g.id medal_id,
g.extension->>'$.level' medal_level,
t.`condition`->>'$.valid_num' reach_num,
e.settings->>'$.every_day_count' every_day_count,
t.name,
g.category_id,
c.desc->>'$.desc' category_desc,
g.thumbs
from " . self::$table . " c
inner join 
 " . GoodsV1Model::$table . " g on c.id = g.category_id
 inner join 
 " . EventTaskModel::$table . " t on t.type = " . EventTaskModel::MEDAL_TYPE . "
 and g.id = t.award->>'$.awards[0].course_id' left join " . EventModel::$table . " e on t.event_id = e.id";

        return MysqlDB::getDB()->queryAll($sql, []);
    }
}