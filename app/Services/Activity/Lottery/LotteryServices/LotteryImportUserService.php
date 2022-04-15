<?php

namespace App\Services\Activity\Lottery\LotteryServices;

use App\Models\LotteryActivityModel;
use App\Models\LotteryImportUserModel;
use App\Models\OperationActivityModel;
use App\Models\LotteryAwardRecordModel;

class LotteryImportUserService
{
    /**
     * 获取用户导入的抽奖机会
     * @param $opActivityId
     * @param $uuid
     * @return int|number
     */
    public static function importUserTimes($opActivityId,$uuid)
    {
        $where = [
            'op_activity_id'=>$opActivityId,
            'uuid'=>$uuid,
        ];
        return LotteryAwardRecordModel::getCount($where);
    }

    /**
     * 追加导流用户
     * @param $opActivityId
     * @param $appendParamsData
     * @return bool
     */
    public static function appendImportUserData($opActivityId, $appendParamsData): bool
    {
        //获取活动数据
        $activityData = LotteryActivityModel::getRecord(['op_activity_id' => $opActivityId]);
        //活动不存在/禁用/已结束,禁止再追加数据
        if (empty($activityData) ||
            $activityData['status'] == OperationActivityModel::ENABLE_STATUS_DISABLE ||
            $activityData['end_time'] < time()
        ) {
            return false;
        }
        return LotteryImportUserModel::batchInsert($appendParamsData);
    }
}