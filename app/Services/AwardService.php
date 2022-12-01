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
        $res = [];

        if (!empty($list)) {
            foreach($list as $v) {
                if (empty($res[$v['mch_billno']])) {
                    $res[$v['mch_billno']] = [
                        'ba_id' => $v['ba_id'],
                        'award_amount' => $v['award_amount'],
                        'status' => $v['status'],
                        'status_msg' => WechatAwardCashDealModel::STATUS_MSG[$v['status']],
                        'result_code' => $v['result_code'],
                        'result_code_msg' => WechatAwardCashDealModel::getWeChatResultCodeMsg($v['result_code'])
                    ];
                }
                $res[$v['mch_billno']]['receipt_number_arr'][] = $v['receipt_number'];
            }
        }

        return [$totalCount, $res];
    }
}