<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/9/4
 * Time: 8:09 PM
 */

namespace App\Models\ModelV1;

use App\Libs\MysqlDB;
use App\Models\CategoryV1Model;
use App\Models\GoodsV1Model;
use App\Models\Model;
use App\Models\PackageExtModel;

class ErpPackageV1Model extends Model
{
    public static $table = 'erp_package_v1';

    // 销售商城 1 音符商城 2 金叶子商城 3 智能陪练商城
    const SALE_SHOP_NOTE = 1;
    const SALE_SHOP_LEAF = 2;
    const SALE_SHOP_AI_PLAY = 3;

    // channel 渠道授权 1 Android 2 IOS 4 公众号 8 ERP
    const CHANNEL_ANDROID = 1;
    const CHANNEL_IOS = 2;
    const CHANNEL_WX = 4;
    const CHANNEL_ERP = 8;

    // 商品包状态 -1 已下架 0 未上架 1 已上架
    const STATUS_OFF_SALE = -1;
    const STATUS_WAIT_SALE = 0;
    const STATUS_ON_SALE = 1;

    const PACKAGE_IS_NOT_CUSTOM = 0; // 标准化产品包
    const PACKAGE_IS_CUSTOM = 1; // 自定义产品包


    /** 产品包时长类型 */
    const PACKAGE_TYPE = [
        CategoryV1Model::DURATION_TYPE_NORMAL => PackageExtModel::PACKAGE_TYPE_NORMAL,
        CategoryV1Model::DURATION_TYPE_TRAIL => PackageExtModel::PACKAGE_TYPE_TRIAL,
        CategoryV1Model::DURATION_TYPE_GIFT => PackageExtModel::PACKAGE_TYPE_NONE
    ];

    /**
     * 获取体验课产品包id
     * @return array|null
     */
    public static function getTrailPackageIds()
    {
        return self::getPackageIds(CategoryV1Model::DURATION_TYPE_TRAIL);
    }

    /**
     * 获取正式课产品包id
     * @return array
     */
    public static function getNormalPackageIds()
    {
        return self::getPackageIds(CategoryV1Model::DURATION_TYPE_NORMAL);
    }


    /**
     * 新产品包ids----已上架、已下架
     * @param $subType
     * @return array
     */
    public static function getPackageIds($subType)
    {
        $db = MysqlDB::getDB();
        $records = $db->queryAll("
select p.id
from " . self::$table . " p
inner join ". ErpPackageGoodsV1Model::$table . " pg on pg.package_id = p.id
inner join " . GoodsV1Model::$table . " g on pg.goods_id = g.id
inner join " . CategoryV1Model::$table . " c on c.id = g.category_id
where pg.status = :status
and c.sub_type = :sub_type
and p.status != :p_status
and p.is_custom = :is_custom", [
            ':status' => ErpPackageGoodsV1Model::SUCCESS_NORMAL,
            ':sub_type' => $subType,
            ':p_status' => self::STATUS_WAIT_SALE,
            ':is_custom' => self::PACKAGE_IS_NOT_CUSTOM
            ]);

        return array_column($records, 'id');
    }

    /**
     * 获取新产品包详情
     * @param $id
     * @return array|null
     */
    public static function getPackage($id)
    {
        $db = MysqlDB::getDB();

        $package = $db->queryAll("
select p.id package_id, p.name package_name, p.channel,
    c.type, c.sub_type, c.callback_app_id,
    g.extension, g.num, g.free_num
from " . self::$table . " p
inner join ". ErpPackageGoodsV1Model::$table . " pg on pg.package_id = p.id
inner join " . GoodsV1Model::$table . " g on pg.goods_id = g.id
inner join " . CategoryV1Model::$table . " c on c.id = g.category_id
where pg.status = :status
and c.type = :type
and p.id = :package_id", [
            ':status' => ErpPackageGoodsV1Model::SUCCESS_NORMAL,
            ':type' => CategoryV1Model::TYPE_DURATION,
            ':package_id' => $id
        ]);

        $package = $package[0];
        $extension = json_decode($package['extension'], 1);
        // 发货方式
        $package['apply_type'] = $extension['apply_type'];
        // 体验课类型
        $package['trial_type'] = !empty($extension['trail_type']) ? $extension['trail_type'] : PackageExtModel::TRIAL_TYPE_NONE;

        // 课包类型
        $package['package_type'] = self::PACKAGE_TYPE[$package['sub_type']];
        $package['app_id'] = $package['callback_app_id'];

        // 时长总天数
        $package['duration_num'] = $package['num'] + $package['free_num'];
        return $package;
    }

    /**
     * 新产品包id、name---已上架
     * @param $subType
     * @param $channel
     * @return array|null
     */
    public static function getPackagesByType($subType, $channel)
    {
        $sql = "
select p.id, p.name
from " . self::$table . " p
inner join ". ErpPackageGoodsV1Model::$table . " pg on pg.package_id = p.id
inner join " . GoodsV1Model::$table . " g on pg.goods_id = g.id
inner join " . CategoryV1Model::$table . " c on c.id = g.category_id
where pg.status = :status
and c.sub_type = :sub_type
and p.status = :p_status
and p.is_custom = :is_custom";
        $map = [
            ':status' => ErpPackageGoodsV1Model::SUCCESS_NORMAL,
            ':sub_type' => $subType,
            ':p_status' => self::STATUS_ON_SALE,
            ':is_custom' => self::PACKAGE_IS_NOT_CUSTOM
        ];

        if (!empty($channel)) {
            $sql .= " and p.channel & :channel";
            $map[':channel'] = $channel;
        }

        $records = MysqlDB::getDB()->queryAll($sql, $map);
        return $records;
    }
}