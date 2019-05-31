<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/24
 * Time: 2:58 PM
 */

namespace App\Models;


use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\Util;

/**
 * 机构账号
 * 密码保存账号和明文密码拼接后的md5
 *
 * Class OrgAccountModel
 * @package App\Models
 */
class OrgAccountModel extends Model
{
    static $table = 'org_account';

    const STATUS_STOP = 0; //停用
    const STATUS_NORMAL = 1; //正常

    public static function getByAccount($account)
    {
        $db = MysqlDB::getDB();
        return $db->get(self::$table, '*', ['account' => $account]);
    }

    public static function getMaxAccount()
    {
        $db = MysqlDB::getDB();
        return $db->max(self::$table, 'account');
    }

    public static function selectByPage($page, $count, $params)
    {
        $where = ' 1=1 ';
        $map = [
            ':status' => Constants::STATUS_TRUE,
            ':now'    => time(),
        ];

        if(!empty($params['account'])) {
            $where .= ' and oa.account = :account ';
            $map[':account'] = $params['account'];
        }
        if(!empty($params['org_id'])) {
            $where .= ' and o.id = :org_id ';
            $map[':org_id'] = $params['org_id'];
        }
        if(!empty($params['org_name'])) {
            $where .= ' and o.name like :org_name ';
            $map[':org_name'] = "%{$params['org_name']}%";
        }
        //license_num=0也是一种状态，所以用isset
        if(isset($params['license_num'])) {
            $where .= ' having license_num = :license_num ';
            $map[':license_num'] = $params['license_num'];
        }

        $oa = OrgAccountModel::$table;
        $o  = OrganizationModel::$table;
        $l  = OrgLicenseModel::$table;

        $limit = Util::limitation($page, $count);

        $db = MysqlDB::getDB();

        $records = $db->queryAll("select oa.org_id, oa.id, o.name org_name, oa.account, oa.status, oa.last_login_time, 
        ifnull((select sum(license_num) from {$l} l where l.org_id = o.id and l.status = :status and l.active_time < :now 
        and l.expire_time > :now), 0) license_num from {$oa} oa left join {$o} o on o.id = oa.org_id 
        where {$where} order by oa.create_time desc {$limit} ", $map);

        $total = $db->queryAll("select count(*) count from (select oa.id, 
        ifnull((select sum(license_num) from {$l} l where l.org_id = o.id and l.status = :status and l.active_time < :now 
        and l.expire_time > :now), 0) license_num from {$oa} oa left join {$o} o on o.id = oa.org_id 
        where {$where}) ss", $map);

        return [$records, $total[0]['count']];
    }
}