<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2020/7/10
 * Time: 下午6:51
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Models\ModelV1\ErpPackageV1Model;

class ThirdPartBillModel extends Model
{
    public static $table = 'third_part_bill';

    const STATUS_SUCCESS = 1; // 请求成功
    const STATUS_FAIL = 2; // 请求失败

    const IS_NEW = 1; // 新注册用户
    const NOT_NEW = 2; // 老用户

    const IGNORE = 1; // 导入数据时忽略当前行标记

    // 1 新产品包 0 旧产品包
    const PACKAGE_V1 = 1;
    const PACKAGE_V1_NOT = 0;

    public static function list($params, $page, $count)
    {
        $where = ' 1=1 ';
        $map = [];

        if(!empty($params['mobile'])) {
            $where .= ' and t.mobile = :mobile ';
            $map[':mobile'] = $params['mobile'];
        }
        if(!empty($params['trade_no'])) {
            $where .= ' and t.trade_no = :trade_no ';
            $map[':trade_no'] = $params['trade_no'];
        }
        if(!empty($params['status'])) {
            $where .= ' and t.status = :status ';
            $map[':status'] = $params['status'];
        }
        if(!empty($params['start_pay_time'])) {
            $where .= ' and t.pay_time >= :start_pay_time ';
            $map[':start_pay_time'] = $params['start_pay_time'];
        }
        if(!empty($params['end_pay_time'])) {
            $where .= ' and t.pay_time <= :end_pay_time ';
            $map[':end_pay_time'] = $params['end_pay_time'];
        }
        if(!empty($params['operator_name'])) {
            $where .= ' and e.name like :operator_name ';
            $map[':operator_name'] = "%{$params['operator_name']}%";
        }
        if(!empty($params['parent_channel_id'])) {
            $where .= ' and t.parent_channel_id = :parent_channel_id ';
            $map[':parent_channel_id'] = $params['parent_channel_id'];
        }
        if(!empty($params['channel_id'])) {
            $where .= ' and t.channel_id = :channel_id ';
            $map[':channel_id'] = $params['channel_id'];
        }
        if(!empty($params['package_id'])) {
            $where .= ' and t.package_id = :package_id ';
            $map[':package_id'] = $params['package_id'];
        }
        if (!empty($params['package_v1'])) {
            $where .= ' and t.package_v1 = ' . self::PACKAGE_V1;
        } else {
            $where .= ' and t.package_v1 = ' . self::PACKAGE_V1_NOT;
        }
        if(!empty($params['is_new'])) {
            $where .= ' and t.is_new = :is_new ';
            $map[':is_new'] = $params['is_new'];
        }

        $t = self::$table;
        $s = StudentModel::$table;
        $e = EmployeeModel::$table;
        $c = ChannelModel::$table;
        $p = ErpPackageModel::$table;
        $p1 = ErpPackageV1Model::$table;

        $limit = Util::limitation($page, $count);

        $db = MysqlDB::getDB();

        $total = $db->queryAll("select count(t.id) count
from {$t} t
       left join {$s} s on t.student_id = s.id 
       inner join {$e} e on e.id = t.operator_id
       where {$where}", $map);

        $records = $db->queryAll("
select t.id,
       t.student_id,
       s.name,
       s.mobile,
       t.trade_no,
       t.is_new,
       t.pay_time,
       t.status,
       t.reason,
       t.create_time,
       t.package_id,
       t.parent_channel_id,
       t.channel_id,
       e.name operator_name,
       case when t.package_v1 = " . self::PACKAGE_V1_NOT . " then (select name from {$p} where id = t.package_id)
            when t.package_v1 = " . self::PACKAGE_V1 . " then (select name from {$p1} where id = t.package_id)
            end package_name,
       c.name channel_name
from {$t} t
       left join {$s} s on t.student_id = s.id 
       inner join {$e} e on e.id = t.operator_id
       left join {$c} c on c.id = t.channel_id
       where {$where} order by t.id desc {$limit}", $map);

        return [$total[0]['count'], $records];
    }
}