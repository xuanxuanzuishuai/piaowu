<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/29
 * Time: 上午9:51
 */

namespace App\Models;


use App\Libs\MysqlDB;

class BillModel extends Model
{
    public static $table = 'bill';

    public static function updateBill($id, $orgId, $data)
    {
        $db = MysqlDB::getDB();
        $affectRows = $db->updateGetCount(self::$table, $data, [
            'id'     => $id,
            'org_id' => $orgId,
        ]);
        return $affectRows;
    }

    public static function selectByPage($orgId, $page, $count, $params)
    {
        $where = [];

        if(!empty($params['student_id'])) {
            $where['b.student_id'] = $params['student_id'];
        }
        if(!empty($params['pya_status'])) {
            $where['b.pay_status'] = $params['pay_status'];
        }
        if(!empty($params['pya_channel'])) {
            $where['b.pay_channel'] = $params['pay_channel'];
        }
        if(!empty($params['source'])) {
            $where['b.source'] = $params['source'];
        }
        if(!empty($orgId)) {
            $where['b.org_id'] = $orgId;
        }
        if(!empty($params['start_create_time'])) {
            $where['b.create_time[>=]'] = $params['start_create_time'];
        }
        if(!empty($params['end_create_time'])) {
            $where['b.create_time[<=]'] = $params['end_create_time'];
        }

        $limitWhere = array_merge($where, [
            'LIMIT' => [($page-1) * $count, $count],
            'ORDER' => ['b.create_time' => 'DESC'],
        ]);

        $db = MysqlDB::getDB();

        $records = $db->select(self::$table . '(b)',
            [
                '[>]' . EmployeeModel::$table . '(e)' => ['b.operator_id' => 'id'],
                '[>]' . StudentModel::$table . '(s)' => ['b.student_id' => 'id'],
            ],
            [
            'e.name(employee_name)',
            's.name(student_name)',
            'b.id',
            'b.org_id',
            'b.student_id',
            'b.pay_status',
            'b.amount',
            'b.trade_no',
            'b.pay_channel',
            'b.operator_id',
            'b.source',
            'b.remark',
            'b.end_time',
            'b.update_time',
            'b.create_time',
        ], $limitWhere);

        $total = $db->count(self::$table . '(b)' , $where);

        return [$records, $total];
    }

    public static function getBillByOrgAndId($orgId, $id)
    {
        $db = MysqlDB::getDB();

        $record = $db->get(self::$table . '(b)',
            ['[>]' . StudentModel::$table . '(s)' => ['b.student_id' => 'id']],
            [
                's.name(student_name)',
                'b.id',
                'b.org_id',
                'b.student_id',
                'b.pay_status',
                'b.amount',
                'b.trade_no',
                'b.pay_channel',
                'b.operator_id',
                'b.source',
                'b.remark',
                'b.end_time',
                'b.update_time',
                'b.create_time',
            ],
            [
                'b.id'     => $id,
                'b.org_id' => $orgId,
            ]);

        return $record;
    }
}