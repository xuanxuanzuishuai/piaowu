<?php

namespace App\Services\Activity\Lottery\LotteryServices;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Models\LotteryAwardInfoModel;

class LotteryAwardInfoService
{
    /**
     * 获取奖品信息
     * @param $opActivityId
     * @return array|mixed
     */
    public static function getAwardInfo($opActivityId)
    {
        $where = [
            'op_activity_id' => $opActivityId,
            'status'         => Constants::STATUS_TRUE
        ];
        $activityInfo = LotteryAwardInfoModel::getRecords($where);
        if (empty($activityInfo)) {
            return [];
        }

        foreach ($activityInfo as $key => $value) {
            $activityInfo[$key]['award_detail'] = json_decode($value['award_detail'], true);
            $activityInfo[$key]['hit_times'] = json_decode($value['hit_times'], true);
            $activityInfo[$key]['img_url'] = AliOSS::replaceCdnDomainForDss($value['img_url']);
        }
        return $activityInfo;
    }

    /**
     * 扣减中奖商品的库存
     * @param $awardId
     * @param $restNum
     * @return int|null
     */
    public static function decreaseRestNum($awardId, $restNum)
    {
        $date = [
            'rest_num[-]' => 1
        ];
        $where = [
            'id'       => $awardId,
            'rest_num' => $restNum,
        ];
        return LotteryAwardInfoModel::batchUpdateRecord($date, $where);
    }
}