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

        $sql = 'select s.id, s.shop_name,s.shop_number, ap.province_name,ac.city_name,ad.district_name,s.detail_address,m.employee_id, e.name as ba_manage_name from shop_info s left join shop_belong_manage m

on s.id = m.shop_id left join employee e on m.employee_id = e.id left join area_province ap on s.province_id = ap.id left join area_city ac on ac.id = s.city_id left join area_district ad on ad.id = s.district_id ' . $where . ' order by s.id desc limit ' . ($page - 1) * $count . ',' . $count;

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

        if (!empty($params['province_id'])) {
            $where .= ' and s.province_id = ' . $params['province_id'];
        }

        if (!empty($params['city_id'])) {
            $where .= ' and s.city_id = ' . $params['city_id'];
        }

        if (!empty($params['district_id'])) {
            $where .= ' and s.district_id = ' . $params['district_id'];
        }


        return $where;
    }

    public static function getShopDetail($shopId)
    {
        $sql = 'SELECT
	s.id shop_id,s.shop_name,s.shop_number,s.create_time,s.province_id,s.city_id,s.district_id,s.detail_address, ap.province_name, ac.city_name, ad.district_name, m.employee_id shop_belong_manage_id, e.`name` manage_name
FROM
	shop_info s
	LEFT JOIN shop_belong_manage m ON s.id = m.shop_id
	LEFT JOIN employee e ON m.employee_id = e.id
	LEFT JOIN area_province ap ON s.province_id = ap.id
	LEFT JOIN area_city ac ON ac.id = s.city_id
	LEFT JOIN area_district ad ON ad.id = s.district_id where s.id = ' . $shopId;

        return MysqlDB::getDB()->queryAll($sql)[0];

    }
}
