<?php

namespace App\Services;

use App\Libs\AliOSS;

class ErpPackageGoodsV1Service
{

    public static function formatGoods($goods)
    {
        foreach ($goods as &$good) {
            $good['thumb'] = AliOSS::replaceShopCdnDomain($good['thumb']);
        }
        return $goods;
    }
}
