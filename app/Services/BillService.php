<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/29
 * Time: 上午10:23
 */

namespace App\Services;


use App\Libs\AliOSS;
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
            $r['pay_status']       = DictService::getKeyValue(Constants::DICT_TYPE_BILL_PAY_STATUS, $r['pay_status']);
            $r['pay_channel']      = DictService::getKeyValue(Constants::DICT_TYPE_BILL_PAY_CHANNEL, $r['pay_channel']);
            $r['source']           = DictService::getKeyValue(Constants::DICT_TYPE_BILL_SOURCE, $r['source']);
            $r['is_disabled']      = DictService::getKeyValue(Constants::DICT_TYPE_BILL_DISABLED, $r['is_disabled']);
            $r['is_enter_account'] = DictService::getKeyValue(Constants::DICT_TYPE_BILL_IS_ENTER_ACCOUNT, $r['is_enter_account']);
            $r['amount']           /= 100;
            $r['sprice']           /= 100;
        }
        return [$records, $total];
    }

    public static function getDetail($billId, $orgId = null)
    {
        $record = BillModel::getDetail($billId, $orgId);

        if(!empty($record)) {
            $record['pay_status']       = DictService::getKeyValue(Constants::DICT_TYPE_BILL_PAY_STATUS, $record['pay_status']);
            $record['pay_channel']      = DictService::getKeyValue(Constants::DICT_TYPE_BILL_PAY_CHANNEL, $record['pay_channel']);
            $record['source']           = DictService::getKeyValue(Constants::DICT_TYPE_BILL_SOURCE, $record['source']);
            $record['is_disabled']      = DictService::getKeyValue(Constants::DICT_TYPE_BILL_DISABLED, $record['is_disabled']);
            $record['is_enter_account'] = DictService::getKeyValue(Constants::DICT_TYPE_BILL_IS_ENTER_ACCOUNT, $record['is_enter_account']);
            $record['amount']           /= 100;
            $record['sprice']           /= 100;

            if(empty($record['credentials_url'])) {
               $record['credentials_url'] = [];
            } else {
                $list = explode(',', $record['credentials_url']);
                $map = [];
                foreach($list as $v) {
                    $map[$v] = AliOSS::signUrls($v);
                }
                $record['credentials_url'] = $map;
            }
        }

        return $record;
    }
}