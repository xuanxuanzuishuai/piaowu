<?php

namespace App\Services\Activity\Lottery\LotteryServices;

use App\Libs\Util;
use App\Models\LotteryAwardRecordModel;

class LotteryAwardRecordService
{
    /**
     * 获取最近一段时间中奖活动的列表
     * @param $opActivityId
     * @return array
     */
    public static function getHitAwardByTime($opActivityId)
    {
        $endTime = time();
        $startTime = $endTime - Util::TIMESTAMP_ONEDAY;
        $activityInfo = LotteryAwardRecordModel::getHitAwardByTime($opActivityId, $startTime, $endTime);
        if (!empty($activityInfo)){
            //处理手机号

            //处理中奖时间
        }

        return $activityInfo ?: [];
    }

    /**
     * 获取用户在指定活动的抽奖次数
     * @param $opActivityId
     * @param $uuid
     * @return int|number
     */
    public static function useLotteryTimes($opActivityId,$uuid)
    {
        $where = [
            'op_activity_id'=>$opActivityId,
            'uuid'=>$uuid,
        ];
        return LotteryAwardRecordModel::getCount($where);
    }

}