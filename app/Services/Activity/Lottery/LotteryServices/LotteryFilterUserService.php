<?php

namespace App\Services\Activity\Lottery\LotteryServices;

use App\Libs\Constants;
use App\Models\LotteryFilterUserModel;

class LotteryFilterUserService
{
    /**
     * 获取筛选用户获得抽奖次数的规则
     * @param $opActivityId
     * @return array
     */
    public static function filterUserTimesRule($opActivityId)
    {
        $where = [
            'op_activity_id' => $opActivityId,
            'status'         => Constants::STATUS_TRUE,
        ];
        return LotteryFilterUserModel::getRecords($where, ['low_pay_amount', 'high_pay_amount', 'times']);
    }

}