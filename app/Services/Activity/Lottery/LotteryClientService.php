<?php

namespace App\Services\Activity\Lottery;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\LotteryActivityModel;
use App\Models\LotteryAwardRecordModel;
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
        if (empty($activityInfo)){
            return [];
        }
        $register = LotteryActivityService::getRegisterChannelId($activityInfo['app_id']);
        $awardInfo = LotteryAwardInfoService::getAwardInfo($params['op_activity_id'],['id','type','name','img_url','level']);
        $hitAwardList = LotteryAwardRecordService::getHitAwardByTime($params['op_activity_id'],$awardInfo);
        $timesInfo = LotteryActivityService::getRestLotteryTimes($params, $activityInfo);
        return [
            'op_activity_id'   => $activityInfo['op_activity_id'],
            'name'             => $activityInfo['name'],
            'title_url'        => $activityInfo['title_url'],
            'start_time'       => $activityInfo['start_time'],
            'end_time'         => $activityInfo['end_time'],
            'status'           => $activityInfo['status'],
            'app_id'           => $activityInfo['app_id'],
            'activity_desc'    => $activityInfo['activity_desc'],
            'rest_times'       => $timesInfo['rest_times'],
            'qualification'    => $timesInfo['qualification'],
            'register_channel' => $register,
            'award_info'       => $awardInfo,
            'hit_award_list'   => $hitAwardList,
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
            $updateRow = LotteryAwardInfoService::decreaseRestNum($hitInfo);
            if (!empty($updateRow)) {
                break;
            }
        }
        //更新相关数据
        $hitInfo['record_id'] = LotteryAwardRecordService::updateHitAwardInfo($awardParams, $hitInfo);

        //投递到队列，发放奖品
        LotteryActivityService::grantLotteryAward($params, $hitInfo);
        $hitInfo['rest_times'] = $awardParams['rest_times'] - 1;
        return $hitInfo;
    }

    /**
     * 获取收货地址信息
     * @param $recordId
     * @return array|false|string
     */
    public static function getAddress($recordId)
    {
        $awardRecordInfo = LotteryAwardRecordModel::getRecord(['id' => $recordId]);

        if (empty($awardRecordInfo['address_detail'])) {
            return [];
        }

        $awardRecordInfo['address_detail'] = json_decode($awardRecordInfo['address_detail'], true);
        return $awardRecordInfo;
    }

    /**
     * 更新收货地址
     * @param $params
     * @return int|null
     * @throws RunTimeException
     */
    public static function modifyAddress($params)
    {
        //查询奖品的发货状态
        $awardRecordInfo =  LotteryAwardRecordModel::getRecord(['id' => $params['record_id']]);
        if ($awardRecordInfo['shipping_status'] != Constants::SHIPPING_STATUS_BEFORE){
            throw new RunTimeException(['not_waiting_send_stop_update_shipping_address']);
        }

        $activityInfo = LotteryActivityModel::getRecord(['op_activity_id' => $awardRecordInfo['op_activity_id']], ['end_time']);
        $modifyEndTime = $activityInfo['end_time'] + Util::TIMESTAMP_ONEWEEK;
        if ($modifyEndTime < time()) {
            throw new RunTimeException(['receive_award_time_error']);
        }
        return LotteryAwardRecordService::modifyAddress($params);
    }

    /**
     * 查看物流信息
     * @param $params
     * @return array
     */
    public static function getExpressDetail($params)
    {
        //查询奖品的发货状态
        $awardRecordInfo = LotteryAwardRecordModel::getRecord(['id' => $params['record_id']]);
        if (empty($awardRecordInfo['unique_id'])) {
            return [];
        }

        return LotteryAwardRecordService::expressDetail($awardRecordInfo['op_activity_id'], $awardRecordInfo['unique_id']);
    }
}