<?php
namespace App\Models;

use App\Libs\MysqlDB;

class GoodsModel extends Model
{
    public static $table = "goods_list";

    /**
     * 获取所有商品列表
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function getGoodsInfo($params, $page, $count)
    {
        $where = self::buildWhere($params);
        $countSql = 'select count(*) count_num from ' . self::$table;
        $countSql .= $where;

        $countInfo = MysqlDB::getDB()->queryAll($countSql);

        $sql = 'select * from ' . self::$table . $where . ' order by id desc limit ' . ($page - 1) * $count . ',' . $count;

        return [MysqlDB::getDB()->queryAll($sql), $countInfo[0]['count_num']];
    }

    private static function buildWhere($params)
    {
        $where = ' where 1 = 1';
        if (!empty($params['goods_name'])) {
            $where .= " and goods_name like '%" . $params['goods_name'] . "%'";
        }

        if (!empty($params['goods_number'])) {
            $where .= ' and goods_number = ' . $params['goods_number'];
        }
        return $where;
    }
}
