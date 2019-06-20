<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/6/18
 * Time: 3:12 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\Util;

class ApprovalConfigModel extends Model
{
    public static $table = "approval_config";

    public static function selectByPage($page, $count, $params)
    {
        $c = ApprovalConfigModel::$table;
        $e = EmployeeModel::$table;
        $where = '';
        $map = [];

        if(!empty($params['type'])) {
            $where .= ' and c.type = :type ';
            $map[':type'] = $params['type'];
        }
        if(isset($params['status'])) {
            $where .= ' and c.status = :status ';
            $map[':status'] = $params['status'];
        }
        if(!empty($params['org_id'])) {
            $where .= ' and c.org_id = :org_id ';
            $map[':org_id'] = $params['org_id'];
        }

        $limit = Util::limitation($page, $count);

        $db = MysqlDB::getDB();

        $records = $db->queryAll("select c.id, c.type, c.levels, c.roles, c.create_time, e.name operator, c.status
        from {$c} c,
             {$e} e
        where c.operator = e.id
          {$where} order by c.create_time desc {$limit}", $map);

        $total = $db->queryAll("select count(*) count from {$c} c where 1=1 {$where}", $map);

        return [$records, $total[0]['count']];
    }
}