<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/10/26
 * Time: 3:56 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Models\ModelV1\ErpPackageGoodsV1Model;

class GiftCodeModel extends Model
{
    public static $table = "gift_code";

    /**
     * 生成方式
     * 1系统生成，2手工生成
     */
    const CREATE_BY_SYSTEM = 1;
    const CREATE_BY_MANUAL = 2;

    /**
     * 生成渠道
     * 1 机构
     * 2 个人
     * 3 其他(停用)
     * 4 erp兑换
     * 5 erp购买订单
     */
    const BUYER_TYPE_ORG = 1;
    const BUYER_TYPE_STUDENT = 2;
    const BUYER_TYPE_OTHER = 3;
    const BUYER_TYPE_ERP_EXCHANGE = 4;
    const BUYER_TYPE_ERP_ORDER = 5;
    const BUYER_TYPE_REFERRAL = 6;
    const BUYER_TYPE_AI_REFERRAL = 7; // AI转介绍

    /**
     * 兑换码状态
     * 0 未兑换
     * 1 已兑换
     * 2 已作废
     */
    const CODE_STATUS_NOT_REDEEMED = 0;
    const CODE_STATUS_HAS_REDEEMED = 1;
    const CODE_STATUS_INVALID = 2;

    /**
     * 激活码时间单位
     * 1 天
     * 2 月
     * 3 年
     */
    const CODE_TIME_DAY = 1;
    const CODE_TIME_MONTH = 2;
    const CODE_TIME_YEAR = 3;
    const CODE_TIME_UNITS = [
        self::CODE_TIME_DAY => 'day',
        self::CODE_TIME_MONTH => 'month',
        self::CODE_TIME_YEAR => 'year',
    ];

    const DEFAULT_DEADLINE_UNIT = 2; //默认激活码有效期单位 月
    const DEFAULT_DEADLINE_NUMBER = 12; //默认激活时间 12
    const DEFAULT_NUM = 1;//默认激活数量


    // 1 新产品包 0 旧产品包
    const PACKAGE_V1 = 1;
    const PACKAGE_V1_NOT = 0;


    /**
     * @param array|string $codes
     * @return bool
     * 获取是否已存在激活码
     */
    public static function codeExists($codes)
    {
        if (empty($codes)) {
            return false;
        }

        $db = MysqlDB::getDB();

        $result = $db->count(self::$table, ['code' => $codes]);
        return $result > 0;
    }

    /**
     * 插入激活码
     * @param $params
     * @return int|mixed|null|string
     */
    public static function InsertCodeInfo($params)
    {
        $data = [];
        $data['code'] = $params['code'];
        $data['generate_channel'] = $params['generate_channel'];
        $data['buyer'] = $params['buyer'];
        $data['buy_time'] = $params['buy_time'];
        $data['valid_num'] = $params['valid_num'];
        $data['valid_units'] = $params['valid_units'];
        $data['generate_way'] = $params['generate_way'];
        $data['operate_user'] = $params['operate_user'];
        $data['create_time'] = $params['create_time'];
        $data['operate_time'] = $params['operate_time'];
        $data['remarks'] = $params['remarks'];
        return MysqlDB::getDB()->insertGetID(self::$table, $data);
    }

    /**
     * @param $params
     * @return array
     * 模糊获取激活码
     */
    public static function getLikeCodeInfo($params)
    {
        $gift_code = self::$table;
        $organization = OrganizationModel::$table;
        $student = StudentModel::$table;
        $employee = EmployeeModel::$table;
        $orgBuyer = GiftCodeModel::BUYER_TYPE_ORG;

        $where = ' where 1 = 1 ';
        $map = [];

        if (!empty($params['code'])) {
            $where .= " and {$gift_code}.code like '%{$params['code']}%'";
        }

        //如果机构不为空，则查询指定机构下的激活码
        if(!empty($params['org_id'])) {
            $where .= " and {$gift_code}.generate_channel = " . self::BUYER_TYPE_ORG;
            $where .= " and {$gift_code}.buyer = :org_id ";
            $map[':org_id'] = $params['org_id'];
        } else {
            if (!empty($params['generate_channel'])) {
                $where .= " and {$gift_code}.generate_channel = :generate_channel";
                $map[':generate_channel'] = $params['generate_channel'];
            }
        }

        if (!empty($params['generate_way'])) {
            $where .= " and {$gift_code}.generate_way = :generate_way";
            $map[':generate_way'] = $params['generate_way'];
        }

        if (isset($params['code_status']) && in_array($params['code_status'], [
            self::CODE_STATUS_NOT_REDEEMED,
            self::CODE_STATUS_HAS_REDEEMED,
            self::CODE_STATUS_INVALID])) {
            $where .= " and {$gift_code}.code_status = :code_status";
            $map[':code_status'] = $params['code_status'];
        }

        if (!empty($params['name'])) {
            $where .= " and {$organization}.name like '%{$params['name']}%'";
        }

        if(!empty($params['buyer_mobile'])) { //购买人手机号
            $where .= " and {$student}.mobile = :buyer_mobile ";
            $map[':buyer_mobile'] = $params['buyer_mobile'];
        }
        if(!empty($params['apply_user_mobile'])) { //使用人手机号
            $where .= " and apply_user.mobile = :apply_user_mobile ";
            $map[':apply_user_mobile'] = $params['apply_user_mobile'];
        }
        if(!empty($params['s_buy_time'])) {
            $where .= " and {$gift_code}.buy_time >= :s_buy_time ";
            $map[':s_buy_time'] = $params['s_buy_time'];
        }
        if(!empty($params['e_buy_time'])) {
            $where .= " and {$gift_code}.buy_time <= :e_buy_time ";
            $map[':e_buy_time'] = $params['e_buy_time'];
        }
        if(!empty($params['s_be_active_time'])) {
            $where .= " and {$gift_code}.be_active_time >= :s_be_active_time ";
            $map[':s_be_active_time'] = $params['s_be_active_time'];
        }
        if(!empty($params['e_be_active_time'])) {
            $where .= " and {$gift_code}.be_active_time <= :e_be_active_time ";
            $map[':e_be_active_time'] = $params['e_be_active_time'];
        }

        $db = MysqlDB::getDB();

        $join = "
            LEFT JOIN {$employee} ON {$employee}.id = {$gift_code}.operate_user
            LEFT JOIN {$organization} ON {$gift_code}.buyer = {$organization}.id AND {$gift_code}.generate_channel = {$orgBuyer}
            LEFT JOIN {$student} ON {$gift_code}.buyer = {$student}.id AND {$gift_code}.generate_channel != {$orgBuyer}
            LEFT JOIN {$student} apply_user ON {$gift_code}.apply_user = apply_user.id ";

        $totalCount = self::getCodeCount($join, $where, $map);

        //格式化分页参数
        list($page, $count) = Util::formatPageCount($params);
        $offset = ($page - 1) * $count;

        $sql = "
            SELECT 
                {$gift_code}.id,
                {$gift_code}.code,
                {$gift_code}.generate_channel,
                {$gift_code}.generate_way,
                {$gift_code}.code_status,
                {$gift_code}.buyer,
                {$gift_code}.buy_time,
                {$gift_code}.apply_user,
                {$gift_code}.valid_num,
                {$gift_code}.valid_units,
                {$gift_code}.be_active_time,
                {$employee}.name operate_user,
                {$gift_code}.operate_user raw_operate_user,
                {$gift_code}.operate_time,
                {$student}.name student_buyer_name,
                {$student}.mobile student_buyer_mobile,
                {$organization}.name org_buyer_name,
                apply_user.name apply_name,
                apply_user.mobile apply_mobile
            FROM
                {$gift_code}
                {$join}
                {$where}
            ORDER BY {$gift_code}.id DESC
            LIMIT $offset, $count
            ";

        $records = $db->queryAll($sql, $map);

        return [$totalCount, $records];
    }


    /**
     * @param $join
     * @param $where
     * @param $map
     * @return mixed
     * 求所需总数
     */
    public static function getCodeCount($join, $where, $map)
    {
        $code = self::$table;
        $db = MysqlDB::getDB();

        $query = "SELECT count(*) AS count FROM {$code}";
        if (!empty($where)) {
            $query = "SELECT count(*) AS count FROM {$code} {$join} {$where}";
        }

        $count = $db->queryAll($query, $map);

        return $count[0]['count'];
    }

    /**
     * 获取激活码信息
     * @param $code
     * @return mixed
     */
    public static function getByCode($code)
    {
        $db = MysqlDB::getDB();
        return $db->get(self::$table, '*', ['code' => $code]);
    }

    /**
     * 获取激活码信息
     * @param $billId
     * @return mixed
     */
    public static function getByBillId($billId)
    {
        $db = MysqlDB::getDB();
        return $db->get(self::$table, '*', ['bill_id' => $billId]);
    }

    /**
     * 获取激活码信息
     * @param $parentBillId
     * @return mixed
     */
    public static function getByParentBillId($parentBillId)
    {
        $db = MysqlDB::getDB();
        return $db->get(self::$table, '*', ['parent_bill_id' => $parentBillId]);
    }

    /**
     * 用户购买正式课的时间--老产品包
     * @param $studentIds
     * @param $oldIds
     * @return array|null
     */
    public static function getOldPaidNormal($studentIds, $oldIds)
    {
        if (empty($oldIds)) {
            return [];
        }
        $db = MysqlDB::getDB();

        $sql = "
    SELECT buyer, buy_time
    FROM " . self::$table . "
    WHERE buyer in (" . implode(',', $studentIds) . ")
    AND bill_package_id IN ( " . implode(',', $oldIds) . " )
    AND package_v1 = " . self::PACKAGE_V1_NOT;
        return $db->queryAll($sql);
    }

    /**
     * 用户购买正式课的时间--新产品包
     * @param $studentIds
     * @param $newIds
     * @return array|null
     */
    public static function getNewPaidNormal($studentIds, $newIds)
    {
        if (empty($newIds)) {
            return [];
        }
        $sql = "
    SELECT buyer, buy_time
    FROM " . self::$table . "
    WHERE buyer in (" . implode(',', $studentIds) . ")
    AND bill_package_id IN ( " . implode(',', $newIds) . " )
    AND package_v1 = " . self::PACKAGE_V1;
        $result = MysqlDB::getDB()->queryAll($sql);
        return $result;
    }

    /**
     * 获取学生买过的体验课类型
     * @param $studentIds
     * @return array
     */
    public static function getPaidTrialPackageType($studentIds)
    {
        $studentIds = implode(',', $studentIds);

        // 老产品包购买记录
        $old = self::getOldPaidTrial($studentIds);

        // 新产品包购买记录
        $new = self::getNewPaidTrial($studentIds);


        return array_column(array_merge($old, $new), 'trial_type', 'buyer');
    }


    /**
     * 体验课购买记录--旧产品包
     * @param $studentIds
     * @return array|null
     */
    public static function getOldPaidTrial($studentIds)
    {
        $db = MysqlDB::getDB();
        $oldSql = $db->queryAll("
SELECT
    code.buyer, ext.trial_type
FROM
    " . self::$table . " code
INNER JOIN " . PackageExtModel::$table . " ext ON ext.package_id = code.bill_package_id
WHERE
    code.buyer in (" . $studentIds . ")
AND
    ext.package_type = " . PackageExtModel::PACKAGE_TYPE_TRIAL . "
AND
    code.package_v1 = " . self::PACKAGE_V1_NOT);

        return $oldSql;
    }

    /**
     * 体验课购买记录--新产品包
     * @param $studentIds
     * @return array|null
     */
    public static function getNewPaidTrial($studentIds)
    {
        $db = MysqlDB::getDB();

        $newSql = $db->queryAll("
SELECT
    code.buyer, g.extension->>'$.trail_type' trial_type
FROM
    " . self::$table . " code
INNER JOIN
    " . ErpPackageGoodsV1Model::$table . " pg ON pg.package_id = code.bill_package_id
    AND pg.status = " . ErpPackageGoodsV1Model::SUCCESS_NORMAL . "
INNER JOIN
    " . GoodsV1Model::$table . " g ON pg.goods_id = g.id
INNER JOIN
    " . CategoryV1Model::$table . " c ON c.id = g.category_id
WHERE
    code.buyer in (" . $studentIds . ")
    AND c.sub_type = " . CategoryV1Model::DURATION_TYPE_TRAIL . "
    AND code.package_v1 = " . self::PACKAGE_V1);

        return $newSql;
    }

}