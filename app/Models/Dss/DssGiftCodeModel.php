<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/4/20
 * Time: 12:02
 */
namespace App\Models\Dss;


use App\Libs\Constants;
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
        $where = [
            self::getTableNameWithDb() . '.buyer' => $allStudentIdArr,
            self::getTableNameWithDb() . '.bill_package_id' => $packageIdArr,
            self::getTableNameWithDb() . '.create_time[>]' => $startTime
        ];
        if (!is_null($isV1Package)) {
            $where[self::getTableNameWithDb() . '.package_v1'] = $isV1Package;
        }
        return $db->get(
            self::getTableNameWithDb(),
            [self::getTableNameWithDb() . '.id'],
            $where
        );
    }
}