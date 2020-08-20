<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/6/1
 * Time: 5:40 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;

class PackageExtModel extends Model
{
    public static $table = 'package_ext';

    /** 发货方式 */
    const APPLY_TYPE_NONE = 0; // 不发货
    const APPLY_TYPE_AUTO = 1; // 自动使用激活码
    const APPLY_TYPE_SMS = 2; // 短信发送激活码

    /** 课包类型类型 */
    const PACKAGE_TYPE_NONE = 0; // 非点评包
    const PACKAGE_TYPE_TRIAL = 1; // 体验包
    const PACKAGE_TYPE_NORMAL = 2; // 正式包

    /** 体验包类型 */
    const TRIAL_TYPE_NONE = 0; // 非体验包
    const TRIAL_TYPE_49 = 1; // 49元包
    const TRIAL_TYPE_9 = 2; // 9.9元包

    /** 产品包业务线 */
    const APP_XYZ = 1; // 真人陪练
    const APP_AI = 8; // 智能陪练

    public static function getPackages($where)
    {
        $ptable = ErpPackageModel::$table;

        $packages = MysqlDB::getDB()->select($ptable, [
            '[>]' . self::$table => ['id' => 'package_id']
        ], [
            $ptable . '.id(package_id)',
            $ptable . '.name(package_name)',
            $ptable . '.app_id',
            $ptable . '.tprice(price)',
            $ptable . '.status(package_status)',
            $ptable . '.channel(package_channel)',
            self::$table . '.package_type',
            self::$table . '.trial_type',
            self::$table . '.apply_type',
            self::$table . '.create_time',
            self::$table . '.update_time',
            self::$table . '.operator',
        ], $where);

        return $packages;
    }

    public static function getByPackageId($packageId)
    {
        $ptable = ErpPackageModel::$table;

        $package = MysqlDB::getDB()->get($ptable, [
            '[>]' . self::$table => ['id' => 'package_id']
        ], [
            $ptable . '.id(package_id)',
            $ptable . '.name(package_name)',
            $ptable . '.app_id',
            $ptable . '.tprice(price)',
            $ptable . '.status(package_status)',
            $ptable . '.channel(package_channel)',
            self::$table . '.package_type',
            self::$table . '.trial_type',
            self::$table . '.apply_type',
            self::$table . '.create_time',
            self::$table . '.update_time',
            self::$table . '.operator',
        ], [$ptable . '.id' => $packageId]);

        return $package;
    }

    public static function getPackagesCount($where)
    {
        unset($where['LIMIT']);

        $ptable = ErpPackageModel::$table;

        $count = MysqlDB::getDB()->count($ptable, [
            '[>]' . self::$table => ['id' => 'package_id']
        ], '*', $where);

        return $count;
    }
}