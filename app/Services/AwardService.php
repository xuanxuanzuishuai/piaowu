<?php


namespace App\Services;


use App\Models\WechatAwardCashDealModel;

class AwardService
{

    /**
     * 红包列表
     * @param $baId
     * @return array
     */
    public static function getAwardList($baId, $page, $count)
    {

        $totalCount = WechatAwardCashDealModel::getCount(['ba_id' => $baId]);

        $list = WechatAwardCashDealModel::getAwardList($baId, $page, $count);


        if (!empty($list)) {
            foreach($list as &$v) {

                $v['status_msg'] = WechatAwardCashDealModel::STATUS_MSG[$v['status']];
                $v['result_code_msg'] = WechatAwardCashDealModel::getWeChatResultCodeMsg($v['result_code']);

                $v['receipt_number_arr'] = array_column(WechatAwardCashDealModel::getAwardRelateReceipt($v['cash_id']), 'receipt_number');
            }
        }

        return [$totalCount, $list];
    }
}