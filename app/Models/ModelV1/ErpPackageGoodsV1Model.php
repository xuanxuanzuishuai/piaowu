<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/9/7
 * Time: 5:52 PM
 */

namespace App\Models\ModelV1;


use App\Libs\MysqlDB;
use App\Models\CategoryV1Model;
use App\Models\GoodsV1Model;
use App\Models\Model;

class ErpPackageGoodsV1Model extends Model
{
    public static $table = 'erp_package_goods_v1';

    const SUCCESS_STOP = 0;
    const SUCCESS_NORMAL = 1;

    public static function goodsListByPackageId($packageId)
    {
        $where = ' pg.package_id = :package_id ';
        $map = [':package_id' => $packageId];

        $where .= ' and pg.status = :status ';
        $map[':status'] = self::SUCCESS_NORMAL;


        $pg = ErpPackageGoodsV1Model::$table;
        $g = GoodsV1Model::$table;
        $c = CategoryV1Model::$table;

        $db = MysqlDB::getDB();

        $records = $db->queryAll("select pg.package_id,
       pg.goods_id,
       g.name goods_name,
       g.num  goods_num,
       g.free_num,
       g.category_id,
       g.`desc` ->> '$.desc' `desc`,
       g.thumbs ->> '$[0]' thumb,
       pg.r_price,
       pg.o_price,
       c.name category_name,
       c.type category_type,
       c.sub_type category_sub_type,
       g.extension
from {$pg} pg
       inner join {$g} g on pg.goods_id = g.id
       inner join {$c} c on c.id = g.category_id
where {$where}", $map);

        return $records;
    }
}