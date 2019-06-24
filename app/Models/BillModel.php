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
    const NOT_DISABLED = 0; //正常
    const IS_DISABLED = 1; //废除

    const PAY_STATUS_UNPAID = 1; //未支付
    const PAY_STATUS_PAID = 2; //已支付

    const NOT_ENTER_ACCOUNT = 0; //不进入学生账户
    const IS_ENTER_ACCOUNT = 1; //进入学生账户

    //添加订单状态
    const ADD_STATUS_APPROVING = 1; //审核中
    const ADD_STATUS_APPROVED = 2; //审核通过
    const ADD_STATUS_REJECTED = 3; //拒绝
    const ADD_STATUS_REVOKED = 4; //撤销

    //废除订单状态
    const DISABLED_STATUS_APPROVING = 1; //审核中
    const DISABLED_STATUS_APPROVED = 2; //审核通过
    const DISABLED_STATUS_REJECTED = 3; //拒绝
    const DISABLED_STATUS_REVOKED = 4; //撤销

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

        if(!empty($params['bill_id'])) {
            $where['b.id'] = $params['bill_id'];
        }
        if(!empty($params['student_id'])) {
            $where['b.student_id'] = $params['student_id'];
        }
        if(!empty($params['pay_status'])) {
            $where['b.pay_status'] = $params['pay_status'];
        }
        if(!empty($params['pay_channel'])) {
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
        if(!empty($params['r_bill_id'])) {
            $where['b.r_bill_id'] = $params['r_bill_id'];
        }
        if(!empty($params['add_status'])) {
            $where['b.add_status'] = $params['add_status'];
        }
        if(!empty($params['disabled_status'])) {
            $where['b.disabled_status'] = $params['disabled_status'];
        }

        $limitWhere = array_merge($where, [
            'LIMIT' => [($page-1) * $count, $count],
            'ORDER' => ['b.is_disabled' => 'ASC','b.create_time' => 'DESC'],
        ]);

        $db = MysqlDB::getDB();

        $records = $db->select(self::$table . '(b)', [
            '[>]' . EmployeeModel::$table . '(e)' => ['b.operator_id' => 'id'],
            '[>]' . StudentModel::$table . '(s)' => ['b.student_id' => 'id'],
            '[><]'. OrganizationModel::$table . '(o)' => ['b.org_id' => 'id'],
            '[>]' . CourseModel::$table . '(c)' => ['b.object_id' => 'id']
        ], [
            'e.name(employee_name)',
            's.name(student_name)',
            'o.name(org_name)',
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
            'b.is_disabled',
            'b.is_enter_account',
            'b.sprice',
            'b.object_id',
            'c.name(object_name)',
            'b.add_status',
            'b.disabled_status',
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
                'b.is_disabled',
                'b.is_enter_account',
                'b.sprice',
                'b.add_status',
                'b.disabled_status',
            ],
            [
                'b.id'     => $id,
                'b.org_id' => $orgId,
            ]);

        return $record;
    }

    public static function getDetail($billId, $orgId = null)
    {
        $where = ' b.id = :id ';
        $map[':id'] = $billId;
        if(!empty($orgId)) {
            $where .= ' and b.org_id = :org_id ';
            $map[':org_id'] = $orgId;
        }

        $e = EmployeeModel::$table;
        $s = StudentModel::$table;
        $o = OrganizationModel::$table;
        $b = BillModel::$table;
        $d = BillExtendModel::$table;
        $n = BillExtendModel::STATUS_NORMAL;
        $c = CourseModel::$table;

        $db = MysqlDB::getDB();

        $records = $db->queryAll("
        select b.*, e.name employee_name, s.name student_name, o.name org_name, c.name object_name,
        (select group_concat(d.credentials_url) from {$d} d where d.bill_id = b.id and d.status = {$n}) credentials_url
        from {$b} b
        left join {$e} e on e.id = b.operator_id
        left join {$s} s on s.id = b.student_id
        inner join {$o} o on b.org_id = o.id
        left join {$c} c on c.id = b.object_id
        where {$where}", $map);

        return !empty($records) ? $records[0] : [];
    }

    public static function selectApprovedBills($startTime, $endTime)
    {
        //'订单编号', '签约人', '学员姓名', '套餐名称', '合同应收金额', '收款日期',
        // '本次支付金额', '支付方式', '支付状态', '收款流水号', '审批状态', '校区审批人', '财务审批人', '描述'

        $e = EmployeeModel::$table;
        $log = ApprovalLogModel::$table;
        $approvalType = ApprovalLogModel::OP_APPROVE;

        $db = MysqlDB::getDB();

        $records = $db->queryAll("SELECT
    b.id, e.name cc_name, s.name student_name, c.name object_name,
    b.sprice, b.end_time, b.amount, b.pay_channel, b.pay_status, b.trade_no, b.add_status, b.remark,
    e1.name campus_op, e2.name finance_op
FROM
    " . BillModel::$table . " b
        INNER JOIN
    " . ApprovalModel::$table . " a ON a.bill_id = b.id
        INNER JOIN
    " . StudentModel::$table . " s ON s.id = b.student_id
        INNER JOIN
    " . CourseModel::$table . " c ON c.id = b.object_id
        INNER JOIN
    {$e} e ON e.id = b.operator_id
        LEFT JOIN
    {$log} log ON log.approval_id = a.id
        AND log.op_type = {$approvalType}
        AND log.level = 0
        LEFT JOIN
    {$log} log1 ON log1.approval_id = a.id
        AND log1.op_type = {$approvalType}
        AND log1.level = 1
        LEFT JOIN
    {$e} e1 ON e1.id = log.operator
        LEFT JOIN
    {$e} e2 ON e2.id = log1.operator
WHERE
    a.type = " . ApprovalModel::TYPE_BILL_ADD . " AND a.status = " . ApprovalModel::STATUS_APPROVED . "
        AND b.pay_status = " . BillModel::PAY_STATUS_PAID . "
        AND b.is_disabled = " . BillModel::NOT_DISABLED . "
        AND add_status = " . BillModel::ADD_STATUS_APPROVED . "
        AND b.end_time >= " . strtotime($startTime) . "
        AND b.end_time < " . strtotime($endTime), []);

        return !empty($records) ? $records : [];
    }
}