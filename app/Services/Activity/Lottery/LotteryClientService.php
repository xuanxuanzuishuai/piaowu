<?php

namespace App\Services\Activity\Lottery;

use App\Libs\Exceptions\RunTimeException;
use App\Services\Activity\Lottery\LotteryServices\LotteryActivityService;
use App\Services\Activity\Lottery\LotteryServices\LotteryAwardInfoService;
use App\Services\Activity\Lottery\LotteryServices\LotteryAwardRecordService;
use App\Services\Activity\Lottery\LotteryServices\LotteryCoreService;

/**
 * 转盘抽奖活动客户端服务文件
 */
class LotteryClientService
{
    /**
     * 获取活动信息
     * @param $params
     * @return array
     */
    public static function activityInfo($params)
    {
        $activityInfo = LotteryActivityService::getActivityConfigInfo($params['op_activity_id']);
        $awardInfo = LotteryAwardInfoService::getAwardInfo($params['op_activity_id']);
        $hitAwardList = LotteryAwardRecordService::getHitAwardByTime($params['op_activity_id']);
        $timesInfo = LotteryActivityService::getRestLotteryTimes($params, $activityInfo);
        return [
            'op_activity_id' => $activityInfo['op_activity_id'],
            'name'           => $activityInfo['name'],
            'title'          => $activityInfo['title'],
            'start_time'     => $activityInfo['start_time'],
            'end_time'       => $activityInfo['end_time'],
            'status'         => $activityInfo['status'],
            'app_id'         => $activityInfo['app_id'],
            'activity_desc'  => $activityInfo['activity_desc'],
            'rest_times'     => $timesInfo['rest_times'],
            'award_info'     => $awardInfo,
            'hit_award_list' => $hitAwardList,
        ];
    }

    /**
     * 开始抽奖
     * @param $params
     * @return array
     * @throws RunTimeException
     */
    public static function hitAwardInfo($params)
    {
        $params['time'] = time();
        $activityInfo = LotteryActivityService::getActivityConfigInfo($params['op_activity_id']);
        //检查活动信息
        LotteryActivityService::checkActivityInfo($activityInfo, $params['time']);

        //整理抽奖所需的基本数据
        $awardParams = LotteryActivityService::getAwardParams($params, $activityInfo);

        $hitInfo = [];
        for ($i = 0; $i < 3; $i++) {
            $hitInfo = LotteryCoreService::LotteryCore($awardParams);
            //扣除奖品库存
            $updateRow = LotteryAwardInfoService::decreaseRestNum($hitInfo['id'], $hitInfo['rest_num']);
            if (!empty($updateRow)) {
                break;
            }
        }
        //更新相关数据
        LotteryAwardRecordService::updateHitAwardInfo($params, $hitInfo);

        return $hitInfo;
    }
}