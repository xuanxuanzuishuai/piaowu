<?php
namespace App\Models;

use App\Libs\MysqlDB;

class ShopInfoModel extends Model
{
    public static $table = "shop_info";

    /**
     * 获取所有门店列表
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function getShopInfo($params, $page, $count)
    {
        $where = self::buildWhere($params);
        $countSql = 'select count(*) count_num from shop_info s left join shop_belong_manage m 

on s.id = m.shop_id left join employee e on m.employee_id = e.id';
        $countSql .= $where;

        $countInfo = MysqlDB::getDB()->queryAll($countSql);

        $sql = 'select s.id, s.shop_name,s.shop_number, m.employee_id, e.name as ba_manage_name from shop_info s left join shop_belong_manage m 

on s.id = m.shop_id left join employee e on m.employee_id = e.id ' . $where . ' order by s.id desc limit ' . ($page - 1) * $count . ',' . $count;

        return [MysqlDB::getDB()->queryAll($sql), $countInfo[0]['count_num']];
    }

    private static function buildWhere($params)
    {
        $where = ' where 1 = 1';
        if (!empty($params['shop_name'])) {
            $where .= " and s.shop_name like '%" . $params['shop_name'] . "%'";
        }

        if (!empty($params['shop_number'])) {
            $where .= ' and s.shop_number = ' . $params['shop_number'];
        }
        return $where;
    }
}
