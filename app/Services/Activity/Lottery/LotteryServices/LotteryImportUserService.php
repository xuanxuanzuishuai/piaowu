<?php

namespace App\Services\Activity\Lottery\LotteryServices;

use App\Libs\Constants;
use App\Models\LotteryActivityModel;
use App\Models\LotteryImportUserModel;
use App\Models\OperationActivityModel;

class LotteryImportUserService
{
    /**
     * 获取用户导入的抽奖配置数据
     * @param $opActivityId
     * @param $uuid
     * @return array
	 */
	public static function importUserTimes($opActivityId, $uuid): array
	{
		$where = [
			'op_activity_id' => $opActivityId,
			'uuid'           => $uuid,
			'status'         => Constants::STATUS_TRUE,
		];
		return LotteryImportUserModel::getRecords($where, ['rest_times', 'order_amount']);
	}

    /**
     * 追加导流用户
     * @param $opActivityId
     * @param $appendParamsData
     * @param $isCover 1覆盖 0追加
     * @return bool
     */
    public static function appendImportUserData($opActivityId, $appendParamsData, $isCover): bool
    {
        $commonDeleteWhere = ['op_activity_id' => $opActivityId];
        //获取活动数据
        $activityData = LotteryActivityModel::getRecord($commonDeleteWhere);
        //活动不存在/禁用/已结束,禁止再追加数据
        if (empty($activityData) ||
            $activityData['status'] == OperationActivityModel::ENABLE_STATUS_DISABLE ||
            $activityData['end_time'] < time()
        ) {
            return false;
        }
        if ($isCover == Constants::STATUS_TRUE) {
            LotteryImportUserModel::batchDelete($commonDeleteWhere);
        }
        return LotteryImportUserModel::batchInsert($appendParamsData);
    }
}