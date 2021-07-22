<?php


namespace App\Models;


use App\Libs\MysqlDB;
use App\Models\Dss\DssEmployeeModel;
use App\Models\Dss\DssStudentModel;
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
     * 获取优惠券详情列表
     * @param $search
     * @param string $type
     * @param int $offset
     * @param int $limit
     * @return array|null
     */
    public static function getActivityInfoList($search, $type = 'list', $offset = 0, $limit = 0)
    {
        $db = self::dbRO();
        $whereStr = '1=1';
        if ($activity_id = $search['activity_id']??0) {   // 活动ID
            $whereStr .= " AND rcrr.activity_id='{$activity_id}'";
        }
        if ($rule_type = $search['rule_type']??0) {   // 活动类型
            $whereStr .= " AND rcrr.rule_type='{$rule_type}'";
        }
        if ($dss_share_employee_id = $search['dss_share_employee_id']??0) {   // 分享员工ID
            $whereStr .= " AND de1.id='{$dss_share_employee_id}'";
        }
        if ($dss_share_employee_name = $search['dss_share_employee_name']??'') {   // 分享员工姓名
            $whereStr .= " AND de1.name='{$dss_share_employee_name}'";
        }
        if ($erp_belong_employee_id = $search['dss_belong_employee_id']??0) {   // 归属员工ID
            $ruleType1 = RtActivityModel::ACTIVITY_RULE_TYPE_SHEQUN;
            $ruleType2 = RtActivityModel::ACTIVITY_RULE_TYPE_KEGUAN;
            $whereStr .= " AND ((rcrr.rule_type='{$ruleType1}' AND de2.id='{$erp_belong_employee_id}') OR (rcrr.rule_type='{$ruleType2}' AND de3.id='{$erp_belong_employee_id}'))";
        }
        if ($erp_belong_employee_name = $search['dss_belong_employee_name']??'') {   // 归属员工姓名
            $ruleType1 = RtActivityModel::ACTIVITY_RULE_TYPE_SHEQUN;
            $ruleType2 = RtActivityModel::ACTIVITY_RULE_TYPE_KEGUAN;
            $whereStr .= " AND ((rcrr.rule_type='{$ruleType1}' AND de2.name='{$erp_belong_employee_name}') OR (rcrr.rule_type='{$ruleType2}' AND de3.name='{$erp_belong_employee_name}'))";
        }
        if ($invite_uid = $search['invite_uid']??0) {   // 邀请人UUID
            $whereStr .= " AND ds1.uuid='{$invite_uid}'";
        }
        if ($invite_mobile = $search['invite_mobile']??'') {   // 邀请人手机号
            $whereStr .= " AND ds1.mobile='{$invite_mobile}'";
        }
        if ($receive_uid = $search['receive_uid']??0) {   // 受邀人UUID
            $whereStr .= " AND ds2.uuid='{$receive_uid}'";
        }
        if ($receive_mobile = $search['receive_mobile']??'') {   // 受邀人手机号
            $whereStr .= " AND ds2.mobile='{$receive_mobile}'";
        }
        if ($status = $search['status']??0) {
            $time = time();
            $noReveivedStatus = self::NOT_REVEIVED_STATUS;
            $unuseStatus = ErpStudentCouponV1Model::STATUS_UNUSE;
            $usedStatus = ErpStudentCouponV1Model::STATUS_USED;
            $expireStatus = ErpStudentCouponV1Model::STATUS_EXPIRE;
            $abandonedStatus = ErpStudentCouponV1Model::STATUS_ABANDONED;
            switch ($status) {
                case 1:   // 未领取
                    $whereStr .= " AND rcrr.status='{$noReveivedStatus}'";
                    break;
                case 2:   // 已领取(未使用)
                    $whereStr .= " AND esc.status='{$unuseStatus}' AND esc.expired_end_time>='{$time}'";
                    break;
                case 3:   // 已使用
                    $whereStr .= " AND esc.status='{$usedStatus}'";
                    break;
                case 4:   // 已过期
                    $whereStr .= " AND (esc.status='{$expireStatus}' OR (esc.status='{$unuseStatus}' AND esc.expired_end_time<'{$time}'))";
                    break;
                case 5:   // 已作废
                    $whereStr .= " AND esc.status='{$abandonedStatus}'";
                    break;
            }
        }
        if ($create_date_start = $search['create_time_start']??'') {   // 推荐时间-开始
            $create_time_start = strtotime($create_date_start);
            $whereStr .= " AND srss.create_time>='{$create_time_start}'";
        }
        if ($create_date_end = $search['create_time_end']??0) {   // 推荐时间-结束
            $create_time_end = strtotime($create_date_end);
            $whereStr .= " AND srss.create_time<='{$create_time_end}'";
        }
        $orderStatusEnable = ErpOrderCouponV1Model::STATUS_ENABLE;
        if ($order_id = $search['order_id']??'') {   // 订单号
            $couponStatusUsed = ErpStudentCouponV1Model::STATUS_USED;
            $whereStr .= " AND eoc.order_id='{$order_id}' AND eoc.status='{$orderStatusEnable}' AND esc.status='{$couponStatusUsed}'";
        }
        if ($has_review_course = $search['has_review_course']??0) {   // 受邀人当前身份
            $whereStr .= " AND ds2.has_review_course='{$has_review_course}'";
        }
        $tableRCRR = self::getTableNameWithDb();
        $tableRA = RtActivityModel::getTableNameWithDb();
        $tableDE = DssEmployeeModel::getTableNameWithDb();
        $tableDS = DssStudentModel::getTableNameWithDb();
        $tableESC = ErpStudentCouponV1Model::getTableNameWithDb();
        $tableEOC = ErpOrderCouponV1Model::getTableNameWithDb();
        $tableSRSS = StudentReferralStudentStatisticsModel::getTableNameWithDb();

        if ($type == 'count') {   //总数
            $sql = "
                SELECT
                    count(1) AS num
                FROM
                    {$tableRCRR} AS `rcrr`
                    INNER JOIN {$tableRA} AS `ra` ON `rcrr`.`activity_id` = `ra`.`activity_id`
                    LEFT JOIN {$tableDE} AS `de1` ON `rcrr`.`employee_uid` = `de1`.`id`
                    INNER JOIN {$tableDS} AS `ds1` ON `rcrr`.`invite_uid` = `ds1`.`id`
                    INNER JOIN {$tableDS} AS `ds2` ON `rcrr`.`receive_uid` = `ds2`.`id`
                    LEFT JOIN {$tableDE} AS `de2` ON `ds1`.`assistant_id` = `de2`.`id`
                    LEFT JOIN {$tableDE} AS `de3` ON `ds1`.`course_manage_id` = `de3`.`id`
                    LEFT JOIN {$tableESC} AS `esc` ON `rcrr`.`student_coupon_id` = `esc`.`id`
                    LEFT JOIN {$tableEOC} AS `eoc` ON `rcrr`.`student_coupon_id` = `eoc`.`student_coupon_id` AND `eoc`.`status` = {$orderStatusEnable}
                    LEFT JOIN {$tableSRSS} AS `srss` ON `rcrr`.`receive_uid` = `srss`.`student_id`
                WHERE {$whereStr}
            ";
            return $db->queryAll($sql);
        }

        $sql = "
            SELECT
                `rcrr`.`id`,
                `rcrr`.`activity_id`,
                `rcrr`.`rule_type`,
                `rcrr`.`student_coupon_id`,
                `rcrr`.`status` AS `record_status`,
                `ra`.`name`,
                `de1`.`id` AS `dss_share_employee_id`,
                `de1`.`name` AS `dss_share_employee_name`,
                `de2`.`id` AS `dss_assistant_id`,
                `de2`.`name` AS `dss_assistant_name`,
                `de3`.`id` AS `dss_course_manage_id`,
                `de3`.`name` AS `dss_course_manage_name`,
                `ds1`.`uuid` AS `invite_uid`,
                `ds1`.`mobile` AS `invite_mobile`,
                `ds2`.`uuid` AS `receive_uid`,
                `ds2`.`mobile` AS `receive_mobile`,
                `ds2`.`has_review_course`,
                `ds2`.`sub_start_date`,
                `ds2`.`sub_end_date`,
                `esc`.`status` AS `coupon_status`,
                `esc`.`expired_start_time`,
                `esc`.`expired_end_time`,
                `eoc`.`order_id`,
                `eoc`.`status` AS `order_status`,
                `srss`.`create_time`
            FROM
                {$tableRCRR} AS `rcrr`
                INNER JOIN {$tableRA} AS `ra` ON `rcrr`.`activity_id` = `ra`.`activity_id`
                LEFT JOIN {$tableDE} AS `de1` ON `rcrr`.`employee_uid` = `de1`.`id`
                INNER JOIN {$tableDS} AS `ds1` ON `rcrr`.`invite_uid` = `ds1`.`id`
                INNER JOIN {$tableDS} AS `ds2` ON `rcrr`.`receive_uid` = `ds2`.`id`
                LEFT JOIN {$tableDE} AS `de2` ON `ds1`.`assistant_id` = `de2`.`id`
                LEFT JOIN {$tableDE} AS `de3` ON `ds1`.`course_manage_id` = `de3`.`id`
                LEFT JOIN {$tableESC} AS `esc` ON `rcrr`.`student_coupon_id` = `esc`.`id`
                LEFT JOIN {$tableEOC} AS `eoc` ON `rcrr`.`student_coupon_id` = `eoc`.`student_coupon_id` AND `eoc`.`status` = {$orderStatusEnable}
                LEFT JOIN {$tableSRSS} AS `srss` ON `rcrr`.`receive_uid` = `srss`.`student_id`
            WHERE {$whereStr}
            ORDER BY
                `rcrr`.`id` DESC
            LIMIT {$offset},{$limit}
        ";
        $records = $db->queryAll($sql);

        return [$records, $whereStr];
    }

}