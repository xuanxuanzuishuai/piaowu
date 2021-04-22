<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/4/20
 * Time: 12:02
 */
namespace App\Models\Dss;

use App\Libs\Constants;
use App\Services\ReferralService;

class DssGiftCodeModel extends DssModel
{
    public static $table = "gift_code";

    /**
     * 兑换码状态
     * 0 未兑换
     * 1 已兑换
     * 2 已作废
     */
    const CODE_STATUS_NOT_REDEEMED = 0;
    const CODE_STATUS_HAS_REDEEMED = 1;
    const CODE_STATUS_INVALID = 2;

    // 1 新产品包 0 旧产品包
    const PACKAGE_V1 = 1;
    const PACKAGE_V1_NOT = 0;

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

    /**
     * 判断用户是否有购买过体验或正式课包
     * @param $studentID
     * @param int $type
     * @param bool $verifyCode 是否验证Code Status
     * @return array|null
     */
    public static function hadPurchasePackageByType($studentID, $type = DssPackageExtModel::PACKAGE_TYPE_TRIAL, $verifyCode = true)
    {
        if (empty($studentID)) {
            return [];
        }
        if ($type == DssPackageExtModel::PACKAGE_TYPE_NORMAL) {
            $packageIdArr = array_column(DssPackageExtModel::getPackages(['package_type' => DssPackageExtModel::PACKAGE_TYPE_NORMAL, 'app_id' => DssPackageExtModel::APP_AI]), 'package_id');
            $v1PackageIdArr = DssErpPackageV1Model::getNormalPackageIds();
        } else {
            $packageIdArr = array_column(DssPackageExtModel::getPackages(['package_type' => DssPackageExtModel::PACKAGE_TYPE_TRIAL, 'app_id' => DssPackageExtModel::APP_AI]), 'package_id');
            $v1PackageIdArr = DssErpPackageV1Model::getTrailPackageIds();
        }

        if (is_array($studentID)) {
            $keyCondition = " AND `buyer` in (" . implode(',', $studentID) . ")";
        } else {
            $keyCondition = " AND `buyer` = " . $studentID;
        }

        $codeStatusList = [self::CODE_STATUS_NOT_REDEEMED, self::CODE_STATUS_HAS_REDEEMED];

        $codeStatusVerifySql = $verifyCode ? " AND `code_status` in (".implode(',', $codeStatusList).") " : '';

        $baseCondition = " `bill_app_id` = " . DssPackageExtModel::APP_AI . $keyCondition . $codeStatusVerifySql;

        $sql = "
        SELECT 
            `id`,
            `buyer`
        FROM
            " . self::$table . "
        WHERE " . $baseCondition;

        //产品包改版
        $packageIdSql = '';
        if (!empty($packageIdArr)) {
            $packageIdSql = " (
                `bill_package_id` in (" . implode(",", $packageIdArr) . ")
                AND `package_v1` = ". self::PACKAGE_V1_NOT . ")";
        }
        $packageIdSqlV1 = '';
        if (!empty($v1PackageIdArr)) {
            $packageIdSqlV1 = " (
                `bill_package_id` in (" . implode(",", $v1PackageIdArr) . ")
                AND `package_v1` = ". self::PACKAGE_V1 . ")";
        }

        if (!empty($packageIdSql) && !empty($packageIdSqlV1)) {
            $sql .= " AND (" . $packageIdSql . " OR " . $packageIdSqlV1 . ")";
        }

        if (empty($packageIdSql) && !empty($packageIdSqlV1)) {
            $sql .= " AND " . $packageIdSqlV1;
        }

        if (!empty($packageIdSql) && empty($packageIdSqlV1)) {
            $sql .= " AND " . $packageIdSql;
        }

        return self::dbRO()->queryAll($sql);
    }

    /**
     * 判断推荐人的所有被推荐人在某个时间点后是否有购买过特定课包
     * @param $refererId
     * @param $packageIdArr
     * @param $startTime
     * @param null $isV1Package
     * @return mixed|null
     */
    public static function refereeBuyCertainPackage($refererId, $packageIdArr, $startTime, $isV1Package = null)
    {
        $refereeAllUser = ReferralService::getRefereeAllUser(Constants::SMART_APP_ID, $refererId);
        if (empty($refereeAllUser)) {
            return NULL;
        }

        $allStudentIdArr = array_column($refereeAllUser, 'student_id');

        $db = self::dbRO();
        $where = ' buyer in (' . implode(',', $allStudentIdArr) . ')';
        $where .= ' AND bill_package_id in (' . implode(',', $packageIdArr) . ')';
        $where .= ' AND create_time > ' . $startTime;
        if (!is_null($isV1Package)) {
            $where .= ' AND package_v1 = ' . $isV1Package;
        }
        $table = self::getTableNameWithDb();
        $sql = "
        SELECT
            id
        FROM
            {$table}
        WHERE
            {$where}
        ";
        return $db->queryAll($sql);
    }

    /**
     * 获取订单信息列表通过订单ID
     * @param $parentBillIds
     * @return array
     */
    public static function getGiftCodeDetailByBillId($parentBillIds)
    {
        //数据库对象
        $db = self::dbRO();
        $giftCodeData = $db->select(
            self::$table . "(gc)",
            [
                "[><]" . DssErpPackageV1Model::$table . "(p)" => ["gc.bill_package_id" => "id"]
            ],
            [
                "gc.bill_package_id",
                "gc.parent_bill_id",
                "gc.bill_amount",
                "gc.code_status",
                "gc.buy_time",
                "p.name (package_name)",
            ],
            [
                "gc.parent_bill_id" => $parentBillIds
            ]);
        return $giftCodeData;
    }


    /**
     * 获取学生体验课包的订单
     * @param $mobiles
     * @param $packageIds
     * @param $packageV1
     * @return array|null
     */
    public static function getStudentTrailOrderList($mobiles, $packageIds, $packageV1)
    {
        $db = self::dbRO();
        $sql = "SELECT 
                        distinct s.mobile
                FROM " . self::$table . " g
                INNER JOIN " . DssStudentModel::$table . " s ON s.id = g.buyer
                WHERE s.mobile IN (" . $mobiles . ") 
                AND g.bill_package_id IN (" . $packageIds . ") 
                AND g.package_v1 = " . $packageV1;
        return $db->queryAll($sql);
    }

    /**
     * 获取用户 [首次|最后一次] 购买 [正式|体验] 信息
     * @param $userId
     * @param int $type
     * @param string $order
     * @return array|null
     */
    public static function getUserFirstPayInfo($userId, $type = DssCategoryV1Model::DURATION_TYPE_NORMAL, $order = '')
    {
        if (empty($userId)) {
            return [];
        }
        $gc = DssGiftCodeModel::getTableNameWithDb();
        $p  = DssErpPackageV1Model::getTableNameWithDb();
        $pg = DssErpPackageGoodsV1Model::getTableNameWithDb();
        $g  = DssGoodsV1Model::getTableNameWithDb();
        $c  = DssCategoryV1Model::getTableNameWithDb();
        $sql = "
        SELECT 
            gc.id,
            gc.buy_time,
            gc.create_time
        FROM  $gc gc
        INNER JOIN  $p p ON gc.bill_package_id = p.id
        INNER JOIN  $pg pg ON pg.package_id = p.id
        INNER JOIN  $g g ON g.id = pg.goods_id
        INNER JOIN  $c c ON c.id = g.category_id
        WHERE 
            gc.buyer = :buyer
            AND c.sub_type = :type
        ORDER BY gc.id $order
        LIMIT 1;
        ";
        $map = [
            ':buyer' => $userId,
            ':type' => $type,
        ];
        $db = self::dbRO();
        $res = $db->queryAll($sql, $map);
        return $res[0] ?? [];
    }

    /**
     * 获取学生购买记录列表
     * @param $studentId
     * @param $subTypes
     * @param $appIds
     * @param $createTime
     * @return array
     */
    public static function getStudentGiftCodeList(int $studentId, $subTypes, $appIds, $createTime = 0)
    {
        $db = self::dbRO();
        $records = $db->select(
            self::$table . "(gc)",
            [
                "[><]" . DssErpPackageV1Model::$table . '(p)' => ['gc.bill_package_id' => 'id'],
                "[><]" . DssErpPackageGoodsV1Model::$table . '(pg)' => ['p.id' => 'package_id'],
                "[><]" . DssGoodsV1Model::$table . "(g)" => ['pg.goods_id' => 'id'],
                "[><]" . DssCategoryV1Model::$table . "(c)" => ['g.category_id' => 'id'],
            ],
            [
                "p.id(package_id)",
                "p.app_id",
                "c.sub_type",
                "gc.create_time",
            ],
            [
                'gc.buyer' => $studentId,
                'gc.create_time[>=]' => $createTime,
                'gc.bill_app_id' => $appIds,
                'c.sub_type' => $subTypes,
                'pg.status' => DssErpPackageGoodsV1Model::SUCCESS_NORMAL,
            ]);
        return $records;
    }


    /**
     * 获取购买着姓名
     * @return array
     */
    public static function getBuyUserName()
    {
        $db = self::dbRO();
        $sql = "SELECT 
                    s.name
                FROM " . self::$table . " g
                INNER JOIN " . DssStudentModel::$table . " s ON s.id = g.buyer
                ORDER BY g.id DESC
                LIMIT 0, 50";
        return $db->queryAll($sql) ?? [];
    }
}