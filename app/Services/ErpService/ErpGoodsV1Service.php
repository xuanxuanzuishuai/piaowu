<?php

namespace App\Services\ErpService;

use App\Models\Erp\ErpGoodsV1Model;

class ErpGoodsV1Service
{
    /**
     * 通过id批量获取商品信息
     * @param $goodsIds
     * @return array|mixed
     */
    public static function getGoodsDataByIds($goodsIds)
    {
        $data = ErpGoodsV1Model::getRecords(['id' => $goodsIds], ['id', 'code']);
        return empty($data) ? [] : $data;
    }
}