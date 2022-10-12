<?php
/**
 * 清晨微信现金奖励交易表
 */

namespace App\Models;



class MorningWechatAwardCashDealModel extends Model
{
    public static $table = 'morning_wechat_award_cash_deal';

    /**
     * 获取状态是发放中的红包记录
     * @param $updateTimeStart
     * @return array
     */
    public static function getStatusIsGiveingRedPack($updateTimeStart = 0)
    {
        $where = [
            'status' => MorningTaskAwardModel::STATUS_GIVE_ING,
        ];
        if (!empty($updateTimeStart)) {
            $where['update_time[>=]'] = $updateTimeStart;
        }
        $list = self::getRecords($where);
        return is_array($list) ? $list : [];
    }
}