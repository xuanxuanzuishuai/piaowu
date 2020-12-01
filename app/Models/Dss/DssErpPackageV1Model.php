<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/4/20
 * Time: 12:02
 */
namespace App\Models\Dss;

class DssErpPackageV1Model extends DssModel
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
    const CHANNEL_H5 = 32;

    // 商品包状态 -1 已下架 0 未上架 1 已上架
    const STATUS_OFF_SALE = -1;
    const STATUS_WAIT_SALE = 0;
    const STATUS_ON_SALE = 1;

    const PACKAGE_IS_NOT_CUSTOM = 0; // 标准化产品包
    const PACKAGE_IS_CUSTOM = 1; // 自定义产品包

    /**
     * 获取体验课产品包id
     * @return array|null
     */
    public static function getTrailPackageIds()
    {
        return self::getPackageIds(DssCategoryV1Model::DURATION_TYPE_TRAIL);
    }

    /**
     * 获取正式课产品包id
     * @return array
     */
    public static function getNormalPackageIds()
    {
        return self::getPackageIds(DssCategoryV1Model::DURATION_TYPE_NORMAL);
    }

    /**
     * 新产品包ids----已上架、已下架
     * @param $subType
     * @return array
     */
    public static function getPackageIds($subType)
    {
        $db = self::dbRO();
        $records = $db->queryAll("
select p.id
from " . self::$table . " p
inner join ". DssErpPackageGoodsV1Model::$table . " pg on pg.package_id = p.id
inner join " . DssGoodsV1Model::$table . " g on pg.goods_id = g.id
inner join " . DssCategoryV1Model::$table . " c on c.id = g.category_id
where pg.status = :status
and c.sub_type = :sub_type
and p.status != :p_status
and p.is_custom = :is_custom", [
            ':status' => DssErpPackageGoodsV1Model::SUCCESS_NORMAL,
            ':sub_type' => $subType,
            ':p_status' => self::STATUS_WAIT_SALE,
            ':is_custom' => self::PACKAGE_IS_NOT_CUSTOM
        ]);

        return array_column($records, 'id');
    }

}