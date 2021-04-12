<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/4/20
 * Time: 12:02
 */
namespace App\Models\Dss;

use App\Libs\UserCenter;

class DssErpPackageV1Model extends DssModel
{
    public static $table = 'erp_package_v1';

    // 销售商城 1 音符商城 2 金叶子商城 3 智能陪练商城 4 魔法石商城 5 真人陪练商城
    const SALE_SHOP_NOTE = 1;
    const SALE_SHOP_LEAF = 2;
    const SALE_SHOP_AI_PLAY = 3;
    const SALE_SHOP_MAGIC_STONE = 4;
    const SALE_SHOP_VIDEO_PLAY = 5;

    // 商品包状态 -1 已下架 0 未上架 1 已上架
    const STATUS_OFF_SALE = -1;
    const STATUS_WAIT_SALE = 0;
    const STATUS_ON_SALE = 1;

    const PACKAGE_IS_NOT_CUSTOM = 0; // 标准化产品包
    const PACKAGE_IS_CUSTOM = 1; // 自定义产品包

    /** 产品包时长类型 */
    const PACKAGE_TYPE = [
        DssCategoryV1Model::DURATION_TYPE_NORMAL => DssPackageExtModel::PACKAGE_TYPE_NORMAL,
        DssCategoryV1Model::DURATION_TYPE_TRAIL => DssPackageExtModel::PACKAGE_TYPE_TRIAL,
        DssCategoryV1Model::DURATION_TYPE_GIFT => DssPackageExtModel::PACKAGE_TYPE_NONE
    ];
    /**
     * 获取体验课产品包id
     * @return array|null
     */
    public static function getTrailPackageIds()
    {
        return array_column(self::getPackageIds(DssCategoryV1Model::DURATION_TYPE_TRAIL), 'package_id');
    }

    /**
     * 获取正式课产品包id
     * @return array
     */
    public static function getNormalPackageIds()
    {
        return array_column(self::getPackageIds(DssCategoryV1Model::DURATION_TYPE_NORMAL), 'package_id');
    }

    /**
     * 新产品包ids----已上架、已下架
     * @param $subType
     * @param $status
     * @return array
     */
    public static function getPackageIds($subType, $status = DssErpPackageGoodsV1Model::SUCCESS_NORMAL)
    {
        $db = self::dbRO();
        $where = [
            'p.status[!]'=>self::STATUS_WAIT_SALE,
            'p.is_custom'=>self::PACKAGE_IS_NOT_CUSTOM,
            'p.sale_shop'=>self::SALE_SHOP_AI_PLAY,
            'c.sub_type'=>$subType,
        ];
        if(!empty($status)){
           $where['pg.status'] = $status;
        }
        $records = $db->select(
            self::$table."(p)",
            [
                "[><]".DssErpPackageGoodsV1Model::$table.'(pg)'=>['p.id'=>'package_id'],
                "[><]".DssGoodsV1Model::$table."(g)"=>['pg.goods_id'=>'id'],
                "[><]".DssCategoryV1Model::$table."(c)"=>['g.category_id'=>'id'],
            ],
            [
                "p.id(package_id)",
                "c.sub_type"
            ],
            $where);
        return $records;
    }

    /**
     * 获取新产品包详情：通过package id获取
     * @param $id
     * @return array|null
     */
    public static function getPackageById($id)
    {
        $db = self::dbRO();
        $package = $db->queryAll("
            select p.id package_id, p.name package_name, p.channel, p.sale_shop, p.status package_status,
                c.type, c.sub_type, c.callback_app_id,
                g.extension, g.num, g.free_num
            from " . self::$table . " p
            inner join " . DssErpPackageGoodsV1Model::$table . " pg on pg.package_id = p.id
            inner join " . DssGoodsV1Model::$table . " g on pg.goods_id = g.id
            inner join " . DssCategoryV1Model::$table . " c on c.id = g.category_id
            where pg.status = :status
            and c.type = :type
            and p.id = :package_id", [
            ':status' => DssErpPackageGoodsV1Model::SUCCESS_NORMAL,
            ':type' => DssCategoryV1Model::TYPE_DURATION,
            ':package_id' => $id
        ]);

        $package = $package[0];
        $extension = json_decode($package['extension'], 1);
        // 发货方式
        $package['apply_type'] = $extension['apply_type'];
        // 体验课类型
        $package['trial_type'] = !empty($extension['trail_type']) ? $extension['trail_type'] : DssPackageExtModel::TRIAL_TYPE_NONE;
        // 课包类型
        $package['package_type'] = self::PACKAGE_TYPE[$package['sub_type']];
        if (in_array($package['sale_shop'], [self::SALE_SHOP_NOTE, self::SALE_SHOP_LEAF, self::SALE_SHOP_AI_PLAY])) {
            $package['app_id'] = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
        } else {
            $package['app_id'] = UserCenter::APP_ID_PRACTICE;
        }
        return $package;
    }

}