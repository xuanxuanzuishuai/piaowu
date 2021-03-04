<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/4/20
 * Time: 12:02
 */
namespace App\Models\Dss;


use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Models\StudentInviteModel;
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
     * 判断用户是否有购买过体验或正式课包
     * @param $studentID
     * @param int $type
     * @param bool $verifyCode 是否验证Code Status
     * @return array|null
     */
    public static function hadPurchasePackageByType($studentID, $type = DssPackageExtModel::PACKAGE_TYPE_TRIAL, $verifyCode = true)
    {
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
        $refereeAllUser = ReferralService::getRefereeAllUser(Constants::SMART_APP_ID, $refererId, StudentInviteModel::REFEREE_TYPE_STUDENT);
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
     * 获取订单信息列表
     * @param $params
     * @param false $isGetCount
     * @return array
     */
    public static function getOrderList($params,$isGetCount = false){
        $giftCode = self::$table;
        $erpPackage = DssErpPackageModel::$table;
        $where =[];
        $returnData = [
            'list' => [],
            'totalCount' => 0
        ];

        if (isset($params['order_id']) && !empty($params['order_id'])) {
            $where["{$giftCode}.parent_bill_id"] = $params['order_id'];
        }

        $join = [
            "[>]{$erpPackage}" => ["{$giftCode}.bill_package_id" => "id"],
        ];

        if($isGetCount) {
            $totalCount = MysqlDB::getDB()->count($giftCode, $join, ["{$giftCode}.id"], $where);
            if (empty($totalCount)){
                return $returnData;
            }
        }

        if(isset($params['page']) && $params['page'] > 0) {
            list($params['page'], $params['count']) = Util::formatPageCount($params);
            $where['LIMIT'] = [($params['page'] - 1) * $params['count'], $params['count']];
        }

        if(isset($params['ORDER']) && !empty($params['ORDER'])) {
            $where['ORDER'] = ["{$giftCode}.create_time" => "DESC"];
        }

        $list = MysqlDB::getDB()->select($giftCode, $join, [
            "{$giftCode}.parent_bill_id",
            "{$erpPackage}.name(package_name)",
            "{$giftCode}.bill_amount",
            "{$giftCode}.code_status",
            "{$giftCode}.buy_time",
            "{$giftCode}.create_time",
            "{$giftCode}.employee_uuid",
        ], $where);

        $returnData['list'] = is_array($list) ? $list :[];
        $returnData['totalCount'] = $totalCount ?? 0;
        return $returnData;
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
     * 获取用户第一次购买正式课信息
     * @param $userId
     * @return array|null
     */
    public static function getUserFirstPayNormalInfo($userId)
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
            gc.create_time
        FROM  $gc gc
        INNER JOIN  $p p ON gc.bill_package_id = p.id
        INNER JOIN  $pg pg ON pg.package_id = p.id
        INNER JOIN  $g g ON g.id = pg.goods_id
        INNER JOIN  $c c ON c.id = g.category_id
        WHERE 
            gc.buyer = :buyer
            AND c.sub_type = ".DssCategoryV1Model::DURATION_TYPE_NORMAL."
        ORDER BY gc.id 
        LIMIT 1;
        ";
        $map = [
            ':buyer' => $userId,
        ];
        $db = self::dbRO();
        $res = $db->queryAll($sql, $map);
        return $res[0] ?? [];
    }
}