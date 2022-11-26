<?php
namespace App\Models;

use App\Libs\MysqlDB;

class BAListModel extends Model
{
    public static $table = "ba_list";

    public static function getBaInfo($baId)
    {
        $sql = 'select b.*, s.shop_name, s.shop_number from ba_list b left join shop_info s 

on b.shop_id = s.id where b.id = ' . $baId;

        return MysqlDB::getDB()->queryAll($sql);
    }

    /**
     * 获取BA经理可看到的ba申请列表
     * @param $employeeId
     * @return array|null
     */
    public static function getBaManageApplyList($employeeId, $page, $count)
    {
        $countSql = 'select count(*) count_num from ba_list a left join shop_belong_manage s on a.shop_id = s.shop_id';
        $countSql .= ' where s.employee_id = ' . $employeeId;

        $countInfo = MysqlDB::getDB()->queryAll($countSql);

        $sql = 'select a.id, a.mobile, a.`name`, a.job_number, p.shop_number, p.shop_name, a.create_time 
from ba_list a left join shop_belong_manage s on a.shop_id = s.shop_id left join shop_info p on a.id = p.id';
        $sql .= ' where s.employee_id = ' . $employeeId;
        $sql .= ' order by a.id desc limit ' . ($page - 1) * $count . ',' . $count;

        return [MysqlDB::getDB()->queryAll($sql), $countInfo[0]['count_num']];
    }

    /**
     * 获取大区经理可看到的ba申请表
     * @param $employeeId
     */
    public static function getRegionManageApplyList($employeeId, $page, $count)
    {
        $countSql = 'select count(*) count_num from ba_list a left join shop_info s on s.id = a.shop_id

left join region_province_relation r on s.province_id = r.province_id
 
left join region_belong_manage m on m.region_id = r.region_id';
        $countSql .= ' where m.employee_id = ' . $employeeId;

        $countInfo = MysqlDB::getDB()->queryAll($countSql);


        $sql = 'select a.id, a.mobile, a.`name`, a.job_number, s.shop_number, s.shop_name, a.create_time,s.province_id, r.region_id, m.employee_id 
from ba_list a left join shop_info s on s.id = a.shop_id

left join region_province_relation r on s.province_id = r.province_id
 
left join region_belong_manage m on m.region_id = r.region_id';

        $sql .= ' where m.employee_id = ' . $employeeId . ' order by a.id desc limit ' . ($page - 1) * $count . ',' . $count;
        return [MysqlDB::getDB()->queryAll($sql), $countInfo[0]['count_num']];
    }


    /**
     * 获取超级管理员看到的ba申请表
     * @param $employeeId
     */
    public static function getSuperApplyList($page, $count)
    {
        $countSql = 'select count(*) count_num from ba_list a left join shop_info s on s.id = a.shop_id';
        $countInfo = MysqlDB::getDB()->queryAll($countSql);

        $sql = 'select a.id, a.mobile, a.`name`, a.job_number, s.shop_number, s.shop_name, a.create_time 
from ba_list a left join shop_info s on s.id = a.shop_id';

        $sql .= ' order by a.id desc limit ' . ($page - 1) * $count . ',' . $count;
        return [MysqlDB::getDB()->queryAll($sql), $countInfo[0]['count_num']];
    }
}