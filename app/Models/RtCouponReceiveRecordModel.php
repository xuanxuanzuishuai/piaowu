<?php


namespace App\Models;


use App\Libs\MysqlDB;
use App\Models\Dss\DssEmployeeModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpEmployeeModel;
use App\Models\Erp\ErpOrderCouponV1Model;
use App\Models\Erp\ErpStudentCouponV1Model;

class RtCouponReceiveRecordModel extends Model
{
    public static $table = "rt_coupon_receive_record";

    const REVEIVED_STATUS = 1; //已领取

    const NOT_REVEIVED_STATUS = 0; //未领取

    public static function info($where, $fields = '*')
    {
        $db = MysqlDB::getDB();
        return $db->get(static::$table, $fields, $where);
    }

    /**
     * @param $search
     * @param $type
     * @return array
     */
    public static function getActivityInfoList($search, $type = 'list', $offset = 0, $limit = 0)
    {
        $db = self::dbRO();
        $whereStr = '1=1';
        if ($activity_id = $search['activity_id']??0) {   // 活动ID
            $whereStr .= " AND a.activity_id='{$activity_id}'";
        }
        if ($rule_type = $search['rule_type']??0) {   // 活动类型
            $whereStr .= " AND a.rule_type='{$rule_type}'";
        }
        if ($dss_employee_id = $search['dss_employee_id']??0) {   // DSS员工ID
            $whereStr .= " AND c.id='{$dss_employee_id}'";
        }
        if ($dss_employee_name = $search['dss_employee_name']??'') {   // DSS员工姓名
            $whereStr .= " AND c.name='{$dss_employee_name}'";
        }
        if ($erp_employee_id = $search['erp_employee_id']??0) {   // ERP员工ID
            $whereStr .= " AND d.id='{$erp_employee_id}'";
        }
        if ($erp_employee_name = $search['erp_employee_name']??'') {   // ERP员工姓名
            $whereStr .= " AND d.name='{$erp_employee_name}'";
        }
        if ($invite_uid = $search['invite_uid']??0) {   // 邀请人UID
            $whereStr .= " AND e.id='{$invite_uid}'";
        }
        if ($invite_mobile = $search['invite_mobile']??'') {   // 邀请人手机号
            $whereStr .= " AND e.mobile='{$invite_mobile}'";
        }
        if ($receive_uid = $search['receive_uid']??0) {   // 受邀人UID
            $whereStr .= " AND f.id='{$receive_uid}'";
        }
        if ($receive_mobile = $search['receive_mobile']??'') {   // 受邀人手机号
            $whereStr .= " AND f.mobile='{$receive_mobile}'";
        }
        if ($status = $search['status']??-1 >= 0) {   // op优惠券状态(0未领取 1已领取)
            $whereStr .= " AND a.status='{$status}'";
        }
        if ($coupon_status = $search['coupon_status']??0) {   // erp优惠券状态(1未使用 2 已使用 3已失效 4已作废)
            $whereStr .= " AND g.status='{$coupon_status}'";
        }
        if ($create_date_start = $search['create_time_start']??'') {   // 推荐时间-开始
            $create_time_start = strtotime($create_date_start);
            $whereStr .= " AND i.create_time>='{$create_time_start}'";
        }
        if ($create_date_end = $search['create_time_end']??0) {   // 推荐时间-结束
            $create_time_end = strtotime($create_date_end);
            $whereStr .= " AND i.create_time<='{$create_time_end}'";
        }
        if ($order_id = $search['order_id']??'') {   // 订单号
            $whereStr .= " AND h.order_id='{$order_id}'";
        }
        if ($has_review_course = $search['has_review_course']??0) {   // 受邀人当前身份
            $whereStr .= " AND f.has_review_course='{$has_review_course}'";
        }
        $tablea = self::getTableNameWithDb();
        $tableb = RtActivityModel::getTableNameWithDb();
        $tablec = DssEmployeeModel::getTableNameWithDb();
        $tabled = ErpEmployeeModel::getTableNameWithDb();
        $tablee = DssStudentModel::getTableNameWithDb();
        $tablef = DssStudentModel::getTableNameWithDb();
        $tableg = ErpStudentCouponV1Model::getTableNameWithDb();
        $tableh = ErpOrderCouponV1Model::getTableNameWithDb();
        $tablei = StudentReferralStudentStatisticsModel::getTableNameWithDb();

        if ($type == 'count') {   //总数
            $sql = "
                SELECT
                    count(1) AS num
                FROM
                    {$tablea} AS `a`
                    INNER JOIN {$tableb} AS `b` ON `a`.`activity_id` = `b`.`activity_id`
                    LEFT JOIN {$tablec} AS `c` ON `a`.`employee_uid` = `c`.`id`
                    LEFT JOIN {$tabled} AS `d` ON `a`.`employee_uid` = `d`.`id`
                    INNER JOIN {$tablee} AS `e` ON `a`.`invite_uid` = `e`.`id`
                    INNER JOIN {$tablef} AS `f` ON `a`.`receive_uid` = `f`.`id`
                    LEFT JOIN {$tableg} AS `g` ON `a`.`student_coupon_id` = `g`.`id`
                    LEFT JOIN {$tableh} AS `h` ON `a`.`student_coupon_id` = `h`.`student_coupon_id`
                    LEFT JOIN {$tablei} AS `i` ON `f`.`id` = `i`.`student_id`
                WHERE {$whereStr}
            ";
            $count = $db->queryAll($sql);
            return $count;
        }

        $sql = "
            SELECT
                `a`.`id`,
                `a`.`activity_id`,
                `a`.`rule_type`,
                `a`.`student_coupon_id`,
                `a`.`status`,
                `b`.`name`,
                `c`.`id` AS `dss_employee_id`,
                `c`.`name` AS `dss_employee_name`,
                `d`.`id` AS `erp_employee_id`,
                `d`.`name` AS `erp_employee_name`,
                `e`.`id` AS `invite_uid`,
                `e`.`mobile` AS `invite_mobile`,
                `f`.`id` AS `receive_uid`,
                `f`.`mobile` AS `receive_mobile`,
                `f`.`has_review_course`,
                `f`.`sub_start_date`,
                `f`.`sub_end_date`,
                `g`.`status` AS `coupon_status`,
                `g`.`expired_start_time`,
                `g`.`expired_end_time`,
                `h`.`order_id`,
                `i`.`create_time`
            FROM
                {$tablea} AS `a`
                INNER JOIN {$tableb} AS `b` ON `a`.`activity_id` = `b`.`activity_id`
                LEFT JOIN {$tablec} AS `c` ON `a`.`employee_uid` = `c`.`id`
                LEFT JOIN {$tabled} AS `d` ON `a`.`employee_uid` = `d`.`id`
                INNER JOIN {$tablee} AS `e` ON `a`.`invite_uid` = `e`.`id`
                INNER JOIN {$tablef} AS `f` ON `a`.`receive_uid` = `f`.`id`
                LEFT JOIN {$tableg} AS `g` ON `a`.`student_coupon_id` = `g`.`id`
                LEFT JOIN {$tableh} AS `h` ON `a`.`student_coupon_id` = `h`.`student_coupon_id`
                LEFT JOIN {$tablei} AS `i` ON `f`.`id` = `i`.`student_id`
            WHERE {$whereStr}
            ORDER BY
                `a`.`id` DESC
            LIMIT {$offset},{$limit}
        ";
        $records = $db->queryAll($sql);

        return [$records, $whereStr];
    }

}