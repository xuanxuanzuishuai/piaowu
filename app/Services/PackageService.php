<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/2/1
 * Time: 11:11
 */

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Erp\ErpGiftGroupV1Model;
use App\Models\Erp\ErpPackageGoodsV1Model;
use App\Models\Erp\ErpPackageV1Model;
use App\Models\Erp\ErpStudentAccountModel;
use App\Libs\Exceptions\RunTimeException;
use App\Models\Dss\DssCategoryV1Model;

class PackageService
{
    /**
     * 课包搜索
     * @param $params
     * @return array|mixed
     */
    public static function packageSearch($params)
    {
        if (!empty($params['name'])) {
            $where['name[~]'] = $params['name'];
        }
        if (empty($where)) {
            return [];
        }

        return DssErpPackageV1Model::getRecords($where, ['id', 'name']);
    }

    /**
     * 新产品包通过subtype查询
     * @param $subType
     * @return array|mixed
     */
    public static function getPackageBySubType($subType)
    {
        $packageIdsData = DssErpPackageV1Model::getPackageIds($subType);
        if (empty($packageIdsData)) {
            return [];
        }
        $packageData = DssErpPackageV1Model::getRecords(['id' => array_column($packageIdsData, 'package_id')], ['id', 'name', 'price_json']);
        foreach ($packageData as &$pv) {
            //课包金额
            $priceJSON = json_decode($pv['price_json'], true);
            $pv['price_money'] = empty($priceJSON[ErpStudentAccountModel::SUB_TYPE_CNY]) ? 0 : ($priceJSON[ErpStudentAccountModel::SUB_TYPE_CNY] / 100);
        }
        return $packageData;
    }


    /**
     * 获取商品列表
     * @param $params
     * @return array
     */
    public static function list($params)
    {
        //查询条件
        // 标准化产品包列表
        $where = ' pg.status = '.ErpPackageGoodsV1Model::SUCCESS_NORMAL.' and p.is_custom = ' . ErpPackageV1Model::PACKAGE_IS_NOT_CUSTOM;
        $having = ' 1=1 ';
        $map = [];
        if (!empty($params['package_id'])) {
            $where .= ' and p.id = :package_id ';
            $map[':package_id'] = $params['package_id'];
        }
        if (!empty($params['package_name'])) {
            $where .= ' and p.name like :package_name ';
            $map[':package_name'] = "%{$params['package_name']}%";
        }
        if (is_numeric($params['package_status'])) {
            $where .= ' and p.status = :package_status ';
            $map[':package_status'] = $params['package_status'];
        }
        if (!empty($params['goods_id'])) {
            $having .= ' and find_in_set(:goods_id, goods_ids) ';
            $map[':goods_id'] = $params['goods_id'];
        }
        if (!empty($params['goods_name'])) {
            $having .= ' and goods_names like :goods_name ';
            $map[':goods_name'] = "%{$params['goods_name']}%";
        }
        // 售卖渠道
        $where .= ' and p.channel &  '.ErpPackageV1Model::CHANNEL_OP_AGENT;
        //获取数据
        $data = ErpPackageV1Model::list($where, $map, $having, $params['page'], $params['count']);
        if (empty($data['count'])) {
            return $data;
        }
        //格式化数据
        $data['list'] = self::formatListData($data['list']);
        return $data;
    }

    /**
     * 格式化列表数据
     * @param $data
     * @return array
     */
    private static function formatListData($data)
    {
        //获取dict数据
        $formatData = [];
        $dict = DictConstants::getErpDict(DictConstants::ERP_PACKAGE_V1_STATUS,DictConstants::ERP_PACKAGE_V1_STATUS['keys']);
        //赠品数据
        $packageIds = array_column($data, 'id');
        $groups = ErpGiftGroupV1Model::getOnlineGroup($packageIds);
        $groupsPackageMap = [];
        foreach ($groups as $group) {
            $groupsPackageMap[$group['package_id']][] = $group['name'];
        }
        $erpHost = $_ENV['ERP_HOST'];
        foreach ($data as $k => $v) {
            //封面图
            if (!empty($v['cover'])) {
                $v['cover'] = AliOSS::replaceShopCdnDomain($v['cover']);
            }
            //库存
            $v['stock'] = empty($v['stock']) ? 0 : $v['stock'];
            //价格
            $priceJSON = json_decode($v['price_json'], 1);
            //总应付价
            $v['price_money'] = ($priceJSON[ErpStudentAccountModel::SUB_TYPE_CNY] ?? 0) / 100;
            //状态
            $v['status_zh'] = $dict[$v['status']];
            $extension = json_decode($v['extension'], 1);
            // 优惠金额
            $v['discount_num'] = !empty($extension['discount_num']) ? $extension['discount_num'] / 100 : 0;
            // 赠品组名称
            $v['gift_groups'] = !empty($groupsPackageMap[$v['id']]) ? implode(',', $groupsPackageMap[$v['id']]) : '';
            //erp后台产品包编辑界面
            $v['erp_href'] = $erpHost . '/#/product_new/packageDetail/' . $v['id'];
            $formatData[$k] = $v;
        }
        return $formatData;
    }

    /**
     * 新产品包详情
     * @param $packageId
     * @return array|bool
     * @throws RunTimeException
     */
    public static function getPackageV1Detail($packageId)
    {
        $package = ErpPackageV1Model::packageDetail($packageId);
        if (empty($package)) {
            throw new RunTimeException(['package_not_available']);
        }

        $goods = ErpPackageGoodsV1Model::goodsListByPackageId($packageId, ErpPackageGoodsV1Model::SUCCESS_NORMAL);

        $package['need_address'] = DssCategoryV1Model::containObject($goods);
        $package['goods'] = ErpPackageGoodsV1Service::formatGoods($goods);
        $package['flags'] = json_decode($package['flags'], 1);
        $package['stock'] = $package['stock'] ?? '0';

        // 介绍图、详情图
        $thumbs = json_decode($package['thumbs'], 1);
        $package['thumbs'] = array_map(function ($url) {
            return AliOSS::replaceShopCdnDomain($url);
        }, $thumbs['thumbs']);

        $details = [];
        if (!empty($thumbs['details']) && is_array($thumbs['details'])) {
            $details = array_map(function ($url) {
                return AliOSS::replaceShopCdnDomain($url);
            }, $thumbs['details']);
        }
        $package['details'] = $details;
        if ($package['end_time'] < time() || (!empty($package['out_time']) && $package['out_time'] < time())) {
            $package['status'] = ErpPackageV1Model::STATUS_OFF_SALE;
        }

        return $package;
    }
}