<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/6/18
 * Time: 3:10 PM
 */

namespace App\Models;


use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\Util;

class ApprovalModel extends Model
{
    public static $table = "approval";

    const TYPE_BILL_ADD = 1;
    const TYPE_BILL_DISABLE = 2;

    const STATUS_WAITING = 1;
    const STATUS_APPROVED = 2;
    const STATUS_REJECTED = 3;
    const STATUS_REVOKED = 4;

    const MAX_LEVELS = 3;

    public static function selectByPage($page, $count, $params)
    {
        $a  = self::$table;
        $e  = EmployeeModel::$table;
        $ac = ApprovalConfigModel::$table;
        $where = ' and ac.status = :ac_status ';
        $map = [':ac_status' => Constants::STATUS_TRUE];

        if(!empty($params['current_role'])) {
            $where .= ' and a.current_role = :current_role';
            $map[':current_role'] = $params['current_role'];
        }
        if(!empty($params['type'])) {
            $where .= ' and a.type = :type ';
            $map[':type'] = $params['type'];
        }
        if(!empty($params['status'])) {
            $where .= ' and a.status = :status ';
            $map[':status'] = $params['status'];
        }
        if(!empty($params['org_id'])) {
            $where .= ' and a.org_id = :org_id ';
            $map[':org_id'] = $params['org_id'];
        }

        $limit = Util::limitation($page, $count);

        $db = MysqlDB::getDB();

        $records = $db->queryAll("select a.bill_id,
               a.id,
               e.name operator,
               a.create_time,
               a.status,
               a.type,
               a.current_role,
               a.current_level
        from {$a} a,
             {$e} e,
             {$ac} ac
        where a.operator = e.id
          and a.config_id = ac.id 
          {$where} order by a.create_time desc {$limit}", $map);

        $total = $db->queryAll("select count(*) count from {$a} a, {$ac} ac where 1=1 and a.config_id = ac.id {$where}", $map);

        return [$records, $total[0]['count']];
    }
}