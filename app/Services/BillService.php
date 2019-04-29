<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/29
 * Time: 上午10:23
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\Util;
use App\Models\BillModel;

class BillService
{
    public static function selectByPage($orgId, $page, $count, $params)
    {
        list($page, $count) = Util::formatPageCount(['page' => $page, 'count' => $count]);

        list($records, $total) = BillModel::selectByPage($orgId, $page, $count, $params);
        foreach($records as &$r) {
            $r['pay_status']  = DictService::getKeyValue(Constants::DICT_TYPE_BILL_PAY_STATUS, $r['pay_status']);
            $r['pay_channel'] = DictService::getKeyValue(Constants::DICT_TYPE_BILL_PAY_CHANNEL, $r['pay_channel']);
            $r['source']      = DictService::getKeyValue(Constants::DICT_TYPE_BILL_SOURCE, $r['source']);
            $r['amount'] /= 100;
        }
        return [$records, $total];
    }
}