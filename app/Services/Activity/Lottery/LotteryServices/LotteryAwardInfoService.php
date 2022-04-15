<?php

namespace App\Services\Activity\Lottery\LotteryServices;

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
            'op_activity_id'=>$opActivityId,
            'status'=>Constants::STATUS_TRUE
        ];
        $activityInfo = LotteryAwardInfoModel::getRecord($where);
        return $activityInfo ?: [];
    }
}