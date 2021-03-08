<?php

namespace App\Models\Erp;

class ErpPackageGoodsV1Model extends ErpModel
{
    public static $table = 'erp_package_goods_v1';
    const SUCCESS_STOP = 0;
    const SUCCESS_NORMAL = 1;

    public static function goodsListByPackageId($packageId, $status = null)
    {
        $where = ' pg.package_id = :package_id ';
        $map = [':package_id' => $packageId];
        if (!is_null($status)) {
            $where .= ' and pg.status = :status ';
            $map[':status'] = $status;
        }

        $pg = ErpPackageGoodsV1Model::$table;
        $g = ErpGoodsV1Model::$table;
        $c = ErpCategoryV1Model::$table;
        $p = ErpPackageV1Model::$table;

        $db = self::dbRO();

        return $db->queryAll("
        SELECT
            pg.package_id,
            pg.goods_id,
            ifnull(p.extension ->> '$.discount_num', 0) discount_num,
            g.name goods_name,
            g.num  goods_num,
            g.free_num,
            g.category_id,
            g.`desc` ->> '$.desc' `desc`,
            g.thumbs ->> '$[0]' thumb,
            pg.r_price,
            pg.o_price,
            pg.accounting_price,
            c.name category_name,
            c.type category_type,
            c.sub_type category_sub_type,
            g.extension,
            g.is_custom
        FROM {$pg} pg
        INNER JOIN {$g} g on pg.goods_id = g.id
        INNER JOIN {$c} c on c.id = g.category_id
        INNER JOIN {$p} p on p.id = pg.package_id
        WHERE {$where}", $map);
    }
}