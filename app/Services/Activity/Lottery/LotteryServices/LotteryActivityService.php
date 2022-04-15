<?php

namespace App\Services\Activity\Lottery\LotteryServices;

use App\Libs\Exceptions\RunTimeException;
use App\Models\LotteryActivityModel;
use App\Models\LotteryAwardRecordModel;
use App\Models\LotteryAwardInfoModel;
use App\Models\LotteryAwardRuleModel;
use App\Models\LotteryFilterUserModel;
use App\Models\OperationActivityModel;

class LotteryActivityService
{
    /**
     * 获取活动基本信息
     * @param $opActivityId
     * @return array|mixed
     */
    public static function getActivityConfigInfo($opActivityId)
    {
        $activityInfo = LotteryActivityModel::getRecord($opActivityId);
        return $activityInfo ?: [];
    }

    /**
     * 获取剩余抽奖次数
     * @param $params
     * @param $activityInfo
     * @return array
     */
    public static function getRestLotteryTimes($params,$activityInfo)
    {
        if (!empty($params['uuid'])) {
            //查询规则获得抽奖次数
            $filterTimes = 0;
            if (!empty($activityInfo['user_source']) && $activityInfo['user_source'] == LotteryActivityModel::USER_SOURCE_FILTER) {
                $filterTimes = LotteryActivityService::filterUserTimes(
                    $params['op_activity_id'],
                    $activityInfo['app_id'],
                    $params['uuid'],
                    $activityInfo['start_pay_time'],
                    $activityInfo['end_pay_time']
                );
            }

            //查询用户导入抽奖机会
            $importTimes = LotteryImportUserService::importUserTimes($params['op_activity_id'], $params['uuid']);
            //用户消耗的抽奖次数
            $useTime = LotteryAwardRecordService::useLotteryTimes($params['op_activity_id'], $params['uuid']);
            $restTimes =  $filterTimes + $importTimes - $useTime;
        } else {
            $restTimes = 0;
        }
        return [
            'filter_times' => $filterTimes ?? 0,
            'import_times' => $importTimes ?? 0,
            'use_times'    => $useTime ?? 0,
            'rest_times'   => $restTimes ?? 0,
        ];
    }

    /**
     * 根据规则计算用户可获得的抽奖次数
     * @param $opActivityId
     * @param $appId
     * @param $uuid
     * @param $startPayTime
     * @param $endPayTime
     * @return int
     */
    public static function filterUserTimes($opActivityId,$appId, $uuid, $startPayTime, $endPayTime)
    {
        $orderInfo = self::getOrderInfo($appId, $uuid, $startPayTime, $endPayTime);
        $orderToTimes = self::orderToTimes($opActivityId,$orderInfo);
        return array_sum($orderToTimes);
    }

    /**
     * 请求订单系统，获取满足条件的订单信息
     * @param $appId
     * @param $uuid
     * @param $startPayTime
     * @param $endPayTime
     * @return array
     */
    public static function getOrderInfo($appId, $uuid, $startPayTime, $endPayTime)
    {
        //请求支付系统接口，并处理
        return [];
    }

    /**
     * 获取订单获得抽奖机会列表
     * @param $opActivityId
     * @param $orderInfo
     * @return array
     */
    public static function orderToTimes($opActivityId,$orderInfo)
    {
        $payTimesRule = LotteryFilterUserService::filterUserTimesRule($opActivityId);
        if (empty($orderInfo) || empty($payTimesRule)){
            return [];
        }

        foreach ($orderInfo as $value) {
            foreach ($payTimesRule as $rule) {
                if (($value['ammount'] >= $rule['low_pay_amount']) && ($value['ammount'] < $rule['high_pay_amount'])) {
                    for ($i = 0; $i < $rule['times']; $i++) {
                        $res[] = $value['ammount'];
                    }
                }
            }
        }
        return $res ?? [];
    }

    /**
     * 活动时间校验
     * @param $activityInfo
     * @param $time
     * @return bool
     * @throws RunTimeException
     */
    public static function checkActivityTime($activityInfo,$time)
    {
        if (empty($activityInfo)){
            throw new RunTimeException(['record_not_found']);
        }

        if ($time < $activityInfo['start_time']){
            throw new RunTimeException(['activity_not_started']);
        }

        if ($time > $activityInfo['end_time']){
            throw new RunTimeException(['activity_is_end']);
        }
        return true;
    }

    /**
     * 整理抽奖需要的参数信息
     * @param $params
     * @param $activityInfo
     * @return mixed
     * @throws RunTimeException
     */
    public static function getAwardParams($params,$activityInfo)
    {
        $filerTimes = 0;
        if ($activityInfo['user_source'] == LotteryActivityModel::USER_SOURCE_FILTER) {
            $orderInfo = self::getOrderInfo($activityInfo['app_id'], $params['uuid'], $activityInfo['start_pay_time'], $activityInfo['end_pay_time']);
            $orderToTimes = self::orderToTimes($activityInfo['op_activity_id'],$orderInfo);
            $filerTimes = array_sum($orderToTimes);
        }
        $importTimes = LotteryImportUserService::importUserTimes($params['op_activity_id'], $params['uuid']);
        $totalTimes = $filerTimes+$importTimes;

        //用户消耗的抽奖次数
        $useTimes = LotteryAwardRecordService::useLotteryTimes($params['op_activity_id'], $params['uuid']);

        if ($totalTimes <= $useTimes){
            throw new RunTimeException(['lottery_times_empty']);
        }

        if ($filerTimes > $useTimes){
            $params['use_type'] = LotteryAwardRecordModel::USE_TYPE_FILTER;
            $params['pay_amount'] = $orderToTimes[$useTimes] ?? -1;
        }else{
            $params['use_type'] = LotteryAwardRecordModel::USE_TYPE_IMPORT;
        }

        $params['award_info'] = LotteryAwardInfoService::getAwardInfo($params['op_activity_id']);
        return $params;
    }

    /**
     * 增加
     * @param $addParamsData
     * @return bool
     */
    public static function add($addParamsData): bool
    {
        return LotteryActivityModel::add($addParamsData);
    }

    /**
     * 编辑
     * @param $opActivityId
     * @param $updateParamsData
     * @return bool
     */
    public static function update($opActivityId, $updateParamsData): bool
    {
        //获取活动数据
        $activityData = LotteryActivityModel::getRecord(['op_activity_id' => $opActivityId]);
        if (empty($activityData)) {
            return false;
        }
        return LotteryActivityModel::update($opActivityId, $updateParamsData);
    }

    /**
     * 搜索活动数据
     * @param $searchParams
     * @param $page
     * @param $limit
     * @return array
     */
    public static function search($searchParams, $page, $limit): array
    {
        //查询条件
        $where = ["id[>=]" => 1];
        if (isset($searchParams['name'])) {
            $where['name'] = trim($searchParams['name']);
        }
        if (isset($searchParams['user_source'])) {
            $where['user_source'] = $searchParams['user_source'];
        }
        //根据不同状态设置不同查询条件
        $mapWhere = OperationActivityModel::showStatusMapWhere($searchParams['show_status']);
        $where += $mapWhere;
        $data = [
            'total' => 0,
            'list'  => [],
        ];
        //获取活动数据
        $total = LotteryActivityModel::getCount($where);
        if ($total <= 0) {
            return $data;
        }
        $data['total'] = $total;
        $where['LIMIT'] = [($page - 1) * $limit, $limit];
        $data['list'] = LotteryActivityModel::getRecords($where);
        return $data;
    }


    /**
     * 详情
     * @param $opActivityId
     * @return array
     */
    public static function detail($opActivityId): array
    {
        $detailData = [
            'base_data'          => [],
            'lottery_times_rule' => [],
            'win_prize_rule'     => [],
            'awards'             => [],
        ];
        $where = ['op_activity_id' => $opActivityId];
        //获取活动数据
        $activityBaseData = LotteryActivityModel::getRecord($where, [
            "op_activity_id",
            "name",
            "title_url",
            "start_time",
            "end_time",
            "max_hit_type",
            "max_hit",
            "day_max_hit_type",
            "day_max_hit",
            "status",
            "user_source",
            "app_id",
            "start_pay_time",
            "end_pay_time",
            "activity_desc"
        ]);
        if (empty($activityBaseData)) {
            return $detailData;
        }
        $detailData['base_data'] = $activityBaseData;
        //扩展数据
        $detailData['lottery_times_rule'] = LotteryFilterUserModel::getRecords($where, [
            "low_pay_amount",
            "high_pay_amount",
            "times"
        ]);
        $detailData['win_prize_rule'] = LotteryAwardRuleModel::getRecords($where, [
            "low_pay_amount",
            "high_pay_amount",
            "award_level"
        ]);
        $detailData['awards'] = LotteryAwardInfoModel::getRecords($where, [
            "name",
            "type",
            "award_detail",
            "img_url",
            "weight",
            "num",
            "hit_times",
        ]);
        return $detailData;
    }
}