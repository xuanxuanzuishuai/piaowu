<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/4/20
 * Time: 12:02
 */
namespace App\Models\Dss;

class DssPackageExtModel extends DssModel
{
    public static $table = 'package_ext';

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

    /** erp - 产品包类型定义 start*/
    /** @var int 正式课分类组合 */
    const CATEGORY_GROUP_FORMAL_COURSE = 1;
    /** @var int 正式时长分类组合 */
    const CATEGORY_GROUP_FORMAL_DUR = 2;
    /** @var int 体验课程分类组 */
    const CATEGORY_GROUP_TRIAL_COURSE = 3;
    /** @var int 体验时长分类组 */
    const CATEGORY_GROUP_TRIAL_DUR = 4;
    /** @var int 赠送课程分类组 */
    const CATEGORY_GROUP_GIFT_COURSE = 5;
    /** @var int 赠送时长分类组 */
    const CATEGORY_GROUP_GIFT_DUR = 6;
    /** erp - 产品包类型定义 end*/

    public static function getPackages($where)
    {
        $ptable = DssErpPackageModel::$table;

        return self::dbRO()->select($ptable, [
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
    }
}