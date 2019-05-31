<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/27
 * Time: 5:24 PM
 */

namespace App\Models;


use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\Util;

class OrgLicenseModel extends Model
{
    public static $table = "org_license";

    /**
     * 获取可用license数量
     * TODO 数量不用及时更新，可以缓存
     * @param $orgId
     * @return number
     */
    public static function getValidNum($orgId)
    {
        $db = MysqlDB::getDB();

        $now = time();
        $num = $db->sum(self::$table, 'license_num', [
            'org_id' => $orgId,
            'status' => Constants::STATUS_TRUE,
            'active_time[<]' => $now,
            'expire_time[>]' => $now,
        ]);

        return $num;
    }

    public static function selectList($params)
    {
        $where = ' 1=1 ';
        $map = [];

        if(!empty($params['org_id'])) {
            $where .= ' and l.org_id = :org_id ';
            $map[':org_id'] = $params['org_id'];
        }
        if(!empty($params['s_create_time'])) {
            $where .= ' and l.create_time >= :s_create_time';
            $map[':s_create_time'] = $params['s_create_time'];
        }
        if(!empty($params['e_create_time'])) {
            $where .= ' and l.create_time <= :e_create_time';
            $map[':e_create_time'] = $params['e_create_time'];
        }
        if(!empty($params['s_active_time'])) {
            $where .= ' and l.active_time >= :s_active_time';
            $map[':s_active_time'] = $params['s_active_time'];
        }
        if(!empty($params['e_active_time'])) {
            $where .= ' and l.active_time <= :e_active_time';
            $map[':e_active_time'] = $params['e_active_time'];
        }
        if(!empty($params['s_expire_time'])) {
            $where .= ' and l.expire_time >= :s_expire_time';
            $map[':s_expire_time'] = $params['s_expire_time'];
        }
        if(!empty($params['e_expire_time'])) {
            $where .= ' and l.expire_time <= :e_expire_time';
            $map[':e_expire_time'] = $params['e_expire_time'];
        }
        if(isset($params['status'])) {
            $where .= ' and l.status = :status ';
            $map[':status'] = $params['status'];
        }
        if(!empty($params['duration'])) {
            $where .= ' and l.duration = :duration ';
            $map[':duration'] = $params['duration'];
        }
        if(!empty($params['duration_unit'])) {
            $where .= ' and l.duration_unit = :duration_unit ';
            $map[':duration_unit'] = $params['duration_unit'];
        }

        $limit = Util::limitation($params['page'], $params['count']);

        $l = OrgLicenseModel::$table;
        $o = OrganizationModel::$table;

        $db = MysqlDB::getDB();
        $records = $db->queryAll("select l.*, o.name org_name from {$l} l, {$o} o where l.org_id = o.id 
                    and {$where} order by l.create_time desc {$limit}", $map);

        $total = $db->queryAll("select count(*) count from {$l} l where {$where}", $map);

        return [$records, $total[0]['count']];
    }
}