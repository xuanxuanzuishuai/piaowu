<?php
namespace App\Models;

use App\Libs\MysqlDB;

class RegionBelongManageModel extends Model
{
    public static $table = "region_belong_manage";

    const STATUS_NORMAL = 1; //正常关联
    const STATUS_ENABLE = 2; //失效

    /**
     * 员工关联的大区信息
     * @param $employeeId
     * @return array|null
     */
    public static function getEmployeeRelateRegion($employeeId)
    {
        $sql = 'select r.id, r.`name`, rm.employee_id 
from region_belong_manage rm inner join area_region r

on rm.region_id = r.id where employee_id = ' . $employeeId . ' and rm.status =' . self::STATUS_NORMAL ;

        return MysqlDB::getDB()->queryAll($sql);
    }
}
