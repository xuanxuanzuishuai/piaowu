<?php

namespace App\Services\Activity\Lottery\LotteryServices;

use App\Libs\Constants;
use App\Models\LotteryActivityModel;
use App\Models\LotteryAwardInfoModel;
use App\Models\LotteryAwardRuleModel;

class LotteryAwardRuleService
{
    /**
     * 获取中奖规则
     * @param $opActivityId
     * @return array|mixed
     */
    public static function getAwardRule($opActivityId)
    {
        $where = [
            'op_activity_id'=>$opActivityId,
            'status'=>Constants::STATUS_TRUE
        ];
        $activityInfo = LotteryAwardRuleModel::getRecords($where);
        return $activityInfo ?: [];
    }
}