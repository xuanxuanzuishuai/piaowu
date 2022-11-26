<?php

namespace App\Models;

use App\Libs\MysqlDB;

class ReceiptApplyModel extends Model
{
    public static $table = 'receipt_apply';

    const CHECK_WAITING = 1; //待审核
    const CHECK_PASS = 2; //审核通过
    const CHECK_CANCEL = 3; //审核作废
    const CHECK_REJECT = 4; //审核驳回

    const CHECK_STATUS_MSG = [
        self::CHECK_WAITING => '待审核',
        self::CHECK_PASS => '审核通过',
        self::CHECK_CANCEL => '审核作废',
        self::CHECK_REJECT => '审核驳回'
    ];


    const ENTER_BACKEND = 1; //后台录入

    private static function buildWhere($params)
    {
        $where = ' where 1 = 1 ';
        if (!empty($params['create_time_start'])) {
            $where .= ' and a.create_time >= ' . strtotime($params['create_time_start']);
        }

        if (!empty($params['create_time_end'])) {
            $where .= ' and a.create_time < ' . strtotime($params['create_time_end']);
        }

        if (!empty($params['check_status'])) {
            $where .= ' and a.check_status = ' . $params['check_status'];
        }

        if (!empty($params['update_time_start'])) {
            $where .= ' and a.update_time >= ' . strtotime($params['update_time_start']);
        }

        if (!empty($params['update_time_end'])) {
            $where .= ' and a.update_time < ' . strtotime($params['update_time_end']);
        }

        if (!empty($params['receipt_number'])) {
            $where .= ' and a.receipt_number = ' . $params['receipt_number'];
        }

        if (!empty($params['receipt_from'])) {
            $where .= ' and a.receipt_from = ' . $params['receipt_from'];
        }

        return $where;
    }

    /**
     * BA经理看到的订单列表
     * @param $employeeId
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function getBAManageReceiptList($employeeId, $params, $page, $count)
    {
        $where = self::buildWhere($params);
        $countSql = 'select count(*) count_num from receipt_apply a left join ba_list l on a.ba_id = l.id 

left join shop_info s on a.shop_id = s.id left join shop_belong_manage m 

on m.shop_id = s.id';
        $countSql .= $where;
        $countSql .= ' and m.employee_id = ' . $employeeId;
        $countInfo = MysqlDB::getDB()->queryAll($countSql);

        $sql = 'select a.id, a.receipt_number, l.name, a.buy_time,s.shop_number,s.shop_name,a.create_time,a.reference_money, a.actual_money,a.check_status,a.system_check_note,a.ba_name,ar.name region_name from receipt_apply a left join ba_list l on a.ba_id = l.id

left join shop_info s on a.shop_id = s.id left join shop_belong_manage m 

on m.shop_id = s.id left join region_province_relation r on s.province_id = r.province_id left join area_region ar on ar.id = r.region_id

left join area_province apv on apv.id = s.province_id left join area_city ac on ac.id = s.city_id ';
        $sql .= $where;

        $sql .= ' and m.employee_id = ' . $employeeId . ' order by a.id desc limit ' . ($page - 1) * $count . ',' . $count;
        return [MysqlDB::getDB()->queryAll($sql), $countInfo[0]['count_num']];
    }


    /**
     * 大区经理看到的订单列表
     * @param $employeeId
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function getRegionManageReceiptList($employeeId, $params, $page, $count)
    {
        $where = self::buildWhere($params);
        $countSql = 'select count(*) count_num from receipt_apply a left join ba_list l on a.ba_id = l.id 

left join shop_info s on a.shop_id = s.id left join region_province_relation r on s.province_id = r.province_id

left join region_belong_manage m on m.region_id = r.region_id';
        $countSql .= $where;
        $countSql .= ' and m.employee_id = ' . $employeeId;
        $countInfo = MysqlDB::getDB()->queryAll($countSql);

        $sql = 'select a.id, a.receipt_number, l.name, a.buy_time,s.shop_number,s.shop_name,a.create_time,a.reference_money, a.actual_money,a.check_status,a.system_check_note,a.ba_name,ar.name region_name  from receipt_apply a left join ba_list l on a.ba_id = l.id

left join shop_info s on a.shop_id = s.id left join region_province_relation r on s.province_id = r.province_id left join area_region ar on ar.id = r.region_id

left join region_belong_manage m on m.region_id = r.region_id';
        $sql .= $where;
        $sql .= ' and m.employee_id = ' . $employeeId . ' order by a.id desc limit ' . ($page - 1) * $count . ',' . $count;
        return [MysqlDB::getDB()->queryAll($sql), $countInfo[0]['count_num']];
    }

    /**
     * 管理员看到的订单列表
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function getSuperReceiptList($params, $page, $count)
    {
        $where = self::buildWhere($params);
        $countSql = 'select count(*) count_num from receipt_apply a left join ba_list l on a.ba_id = l.id 

left join shop_info s on a.shop_id = s.id';
        $countSql .= $where;
        $countInfo = MysqlDB::getDB()->queryAll($countSql);

        $sql = 'select a.id, a.receipt_number, l.name, a.buy_time,s.shop_number,s.shop_name,a.create_time,a.reference_money, a.actual_money,a.check_status,a.system_check_note,a.ba_name,ar.name region_name  from receipt_apply a left join ba_list l on a.ba_id = l.id

left join shop_info s on a.shop_id = s.id left join region_province_relation r on s.province_id = r.province_id left join area_region ar on ar.id = r.region_id';
        $sql .= $where;
        $sql .= ' order by a.id desc limit '  . ($page - 1) * $count . ',' . $count;
        return [MysqlDB::getDB()->queryAll($sql), $countInfo[0]['count_num']];
    }
}
