<?php

namespace App\Services\Activity\Lottery;

use App\Libs\Exceptions\RunTimeException;
use App\Models\LotteryActivityModel;
use App\Models\LotteryAwardRecordModel;
use App\Services\Activity\Lottery\LotteryServices\LotteryActivityService;
use App\Services\Activity\Lottery\LotteryServices\LotteryAwardInfoService;
use App\Services\Activity\Lottery\LotteryServices\LotteryAwardRecordService;
use App\Services\Activity\Lottery\LotteryServices\LotteryAwardRuleService;
use App\Services\Activity\Lottery\LotteryServices\LotteryCoreService;
use App\Services\Activity\Lottery\LotteryServices\LotteryImportUserService;

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
        $timesInfo = LotteryActivityService::getRestLotteryTimes($params,$activityInfo);
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
        $time = time();
        $activityInfo = LotteryActivityService::getActivityConfigInfo($params['op_activity_id']);
        //检查活动信息
        LotteryActivityService::checkActivityTime($activityInfo,$time);

        //整理抽奖的基本信息
        $awardParams = LotteryActivityService::getAwardParams($params,$activityInfo);

        LotteryCoreService::LotteryCore($awardParams);
        return [];

    }

}