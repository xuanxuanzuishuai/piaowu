<?php
namespace App\Models;
use App\Libs\MysqlDB;

class BAApplyModel extends Model
{
    public static $table = "ba_apply";

    const APPLY_WAITING = 1;
    const APPLY_PASS    = 2;
    const APPLY_REJECT  = 3;

    const STATUS_MSG = [
        self::APPLY_WAITING => '待审核',
        self::APPLY_PASS => '审核通过',
        self::APPLY_REJECT => '审核拒绝'
    ];


    /**
     * 获取BA经理可看到的ba申请列表
     * @param $employeeId
     * @return array|null
     */
    public static function getBaManageApplyList($employeeId, $params, $page, $count)
    {
        $where = self::buildWhere($params);
        $countSql = 'select count(*) count_num from ba_apply a left join shop_belong_manage s on a.shop_id = s.shop_id';
        $countSql .= $where;
        $countSql .= ' and s.employee_id = ' . $employeeId;

        $countInfo = MysqlDB::getDB()->queryAll($countSql);

        $sql = 'select a.id, a.mobile, a.`name`, a.job_number, a.idcard, p.shop_number, p.shop_name, a.create_time,a.check_status
from ba_apply a left join shop_belong_manage s on a.shop_id = s.shop_id left join shop_info p on a.id = p.id';
        $sql .= $where;
        $sql .= ' and s.employee_id = ' . $employeeId;
        $sql .= ' order by a.id desc limit ' . ($page - 1) * $count . ',' . $count;

        return [MysqlDB::getDB()->queryAll($sql), $countInfo[0]['count_num']];
    }

    /**
     * 获取大区经理可看到的ba申请表
     * @param $employeeId
     */
    public static function getRegionManageApplyList($employeeId, $params, $page, $count)
    {
        $where = self::buildWhere($params);
        $countSql = 'select count(*) count_num from ba_apply a left join shop_info s on a.shop_id = s.id

left join region_province_relation r on s.province_id = r.province_id
 
left join region_belong_manage m on m.region_id = r.region_id';
        $countSql .= $where;
        $countSql .= ' and m.employee_id = ' . $employeeId;

        $countInfo = MysqlDB::getDB()->queryAll($countSql);


        $sql = 'select a.id, a.mobile, a.`name`, a.job_number, a.idcard, s.shop_number, s.shop_name, a.create_time,a.check_status,s.province_id, r.region_id, m.employee_id
from ba_apply a left join shop_info s on a.shop_id = s.id

left join region_province_relation r on s.province_id = r.province_id
 
left join region_belong_manage m on m.region_id = r.region_id';

        $sql .= $where;

        $sql .= ' and m.employee_id = ' . $employeeId . ' order by a.id desc limit ' . ($page - 1) * $count . ',' . $count;
        return [MysqlDB::getDB()->queryAll($sql), $countInfo[0]['count_num']];
    }


    /**
     * 获取超级管理员看到的ba申请表
     * @param $employeeId
     */
    public static function getSuperApplyList($params, $page, $count)
    {
        $where = self::buildWhere($params);
        $countSql = 'select count(*) count_num from ba_apply a left join shop_info s on a.shop_id = s.id';
        $countInfo = MysqlDB::getDB()->queryAll($countSql .= $where);

        $sql = 'select a.id, a.mobile, a.`name`, a.job_number, a.idcard, s.shop_number, s.shop_name, a.create_time,a.check_status
from ba_apply a left join shop_info s on a.shop_id = s.id';

        $sql .= $where;
        $sql .= ' order by a.id desc limit ' . ($page - 1) * $count . ',' . $count;
        return [MysqlDB::getDB()->queryAll($sql), $countInfo[0]['count_num']];
    }

    private static function buildWhere($params)
    {
        $where = ' where 1 = 1 ';
        if (!empty($params['register_time_start'])) {
            $where .= ' and a.create_time >= ' . strtotime($params['register_time_start']);
        }

        if (!empty($params['register_time_end'])) {
            $where .= ' and a.create_time < ' . strtotime($params['register_time_end']);
        }

        if (!empty($params['check_status'])) {
            $where .= ' and a.check_status = ' . $params['check_status'];
        }

        if (!empty($params['mobile'])) {
            $where .= ' and a.mobile = ' . $params['mobile'];
        }

        return $where;
    }
}