<?php

namespace App\Services\Activity\Lottery\LotteryServices;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\Erp\ErpStudentOrderV1Model;
use App\Models\LotteryActivityModel;
use App\Models\LotteryAwardRecordModel;
use App\Models\LotteryAwardInfoModel;
use App\Models\LotteryAwardRuleModel;
use App\Models\LotteryFilterUserModel;
use App\Models\OperationActivityModel;
use App\Services\DictService;
use App\Services\Queue\QueueService;

class LotteryActivityService
{
    /**
     * 获取活动基本信息
     * @param $opActivityId
     * @return array|mixed
     */
    public static function getActivityConfigInfo($opActivityId)
    {
        $where = [
            'op_activity_id' => $opActivityId
        ];
        $activityInfo = LotteryActivityModel::getRecord($where);
        $activityInfo['name'] = Util::textDecode($activityInfo['name']);
        $activityInfo['activity_desc'] = Util::textDecode($activityInfo['activity_desc']);
        if (!empty($activityInfo['title_url'])) {
            $activityInfo['title_url'] = AliOSS::replaceCdnDomainForDss($activityInfo['img_url']);
        }
        return $activityInfo ?: [];
    }

    /**
     * 获取用户注册渠道
     * @param $appId
     * @return int
     */
    public static function getRegisterChannelId($appId): int
    {
        return (int)DictService::getKeyValue(DictConstants::LOTTERY_CONFIG, $appId);
    }

    /**
     * 获取剩余抽奖次数
     * @param $params
     * @param $activityInfo
     * @return array
     */
    public static function getRestLotteryTimes($params, $activityInfo)
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
            $restTimes = $filterTimes + $importTimes - $useTime;
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
    public static function filterUserTimes($opActivityId, $appId, $uuid, $startPayTime, $endPayTime)
    {
        $orderInfo = self::getOrderInfo($appId, $uuid, $startPayTime, $endPayTime);
        $orderToTimes = self::orderToTimes($opActivityId, $orderInfo);
        return count($orderToTimes);
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
        if ($appId == Constants::REAL_APP_ID) {
            $saleShop = Constants::SALE_SHOP_VIDEO_PLAY;
        } elseif ($appId == Constants::SMART_APP_ID) {
            $saleShop = Constants::SALE_SHOP_AI_PLAY;
        } else {
            return [];
        }

        $res = [];
        //请求支付系统接口，并处理
        $request = [
            'sale_shop'      => $saleShop,
            'student_uuid'   => $uuid,
            'order_status'   => ErpStudentOrderV1Model::STATUS_PAID,
            'start_pay_time' => $startPayTime,
            'end_pay_time'   => $endPayTime,
            'page'           => 1,
            'count'          => 100,
        ];
        $response = (new Erp())->orderSearch($request);
        if (!empty($response)) {
            $res = $response['data'];
        }
        while (true) {
            if ($response['total_count'] > ($request['page'] * $request['count'])) {
                $request['page'] += 1;
                $response = (new Erp())->orderSearch($request);
                $res = array_merge($res, $response['data']);
            }
            break;
        }

        if (!empty($res)) {
            foreach ($res as $value) {
                if (empty($value['payment'])) {
                    continue;
                }
                $data[] = intval($value['payment']['exchange_rate'] * $value['payment']['direct_num'] / 10000);
            }
        }
        return $data ?? [];
    }

    /**
     * 获取订单获得抽奖机会列表
     * @param $opActivityId
     * @param $orderInfo
     * @return array
     */
    public static function orderToTimes($opActivityId, $orderInfo)
    {
        $payTimesRule = LotteryFilterUserService::filterUserTimesRule($opActivityId);
        if (empty($orderInfo) || empty($payTimesRule)) {
            return [];
        }

        foreach ($orderInfo as $value) {
            foreach ($payTimesRule as $rule) {
                if (($value >= $rule['low_pay_amount']) && ($value < $rule['high_pay_amount'])) {
                    for ($i = 0; $i < $rule['times']; $i++) {
                        $res[] = $value;
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
    public static function checkActivityInfo($activityInfo, $time)
    {
        if (empty($activityInfo)) {
            throw new RunTimeException(['record_not_found']);
        }

        if ($time < $activityInfo['start_time']) {
            throw new RunTimeException(['activity_not_started']);
        }

        if ($time > $activityInfo['end_time']) {
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
    public static function getAwardParams($params, $activityInfo)
    {
        $filerTimes = 0;
        if ($activityInfo['user_source'] == LotteryActivityModel::USER_SOURCE_FILTER) {
            $orderInfo = self::getOrderInfo($activityInfo['app_id'], $params['uuid'], $activityInfo['start_pay_time'],
                $activityInfo['end_pay_time']);
            $orderToTimes = self::orderToTimes($activityInfo['op_activity_id'], $orderInfo);
            $filerTimes = count($orderToTimes);
        }
        $importTimes = LotteryImportUserService::importUserTimes($params['op_activity_id'], $params['uuid']);
        $totalTimes = $filerTimes + $importTimes;

        //用户消耗的抽奖次数
        $useTimes = LotteryAwardRecordService::useLotteryTimes($params['op_activity_id'], $params['uuid']);
        $params['rest_times'] = $totalTimes - $useTimes;
        if ($totalTimes <= $useTimes) {
            throw new RunTimeException(['lottery_times_empty']);
        }

        if ($filerTimes > $useTimes) {
            $params['use_type'] = LotteryAwardRecordModel::USE_TYPE_FILTER;
            $params['pay_amount'] = $orderToTimes[$useTimes] ?? -1;
        } else {
            $params['use_type'] = LotteryAwardRecordModel::USE_TYPE_IMPORT;
        }

        $params['max_hit'] = $activityInfo['max_hit'];
        $params['day_max_hit'] = $activityInfo['day_max_hit'];
        return $params;
    }

    /**
     * 更新活动相关统计数据
     * @param $opActivityId
     * @param $fields
     * @return int|null
     */
    public static function updateAfterHitInfo($opActivityId, $fields)
    {
        if (in_array('rest_award_num', $fields)) {
            $update['rest_award_num[-]'] = 1;
        }

        if (in_array('hit_times', $fields)) {
            $update['hit_times[+]'] = 1;
        }

        if (in_array('join_num', $fields)) {
            $update['join_num[+]'] = 1;
        }

        if (!empty($update)) {
            return LotteryActivityModel::batchUpdateRecord($update, ['op_activity_id' => $opActivityId]);
        }
        return 0;
    }

    /**
     * 投递奖品信息到发奖队列
     * @param $params
     * @param $hitInfo
     */
    public static function grantLotteryAward($params, $hitInfo)
    {
        if (!empty($hitInfo) && !in_array($hitInfo['type'],
                [Constants::AWARD_TYPE_EMPTY, Constants::AWARD_TYPE_TYPE_ENTITY])) {
            switch ($hitInfo['type']) {
                case Constants::AWARD_TYPE_GOLD_LEAF:
                case Constants::AWARD_TYPE_MAGIC_STONE:
                case Constants::AWARD_TYPE_TYPE_NOTE:
                    $batchId = Util::getBatchId();
            }
            $data = [
                'record_id'           => $hitInfo['record_id'],
                'type'                => $hitInfo['type'],
                'uuid'                => $params['uuid'],
                'student_id'          => $params['student_id'],
                'common_award_id'     => $hitInfo['award_detail']['common_award_id'],
                'common_award_amount' => $hitInfo['award_detail']['common_award_amount'],
                'remark'              => '抽奖活动赠送',
                'batch_id'            => $batchId ?? '',
            ];
            QueueService::lotteryGrantAward($data);
        }
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
        if (!empty($searchParams['name'])) {
            $where['name[~]'] = trim($searchParams['name']);
        }
        if (!empty($searchParams['user_source'])) {
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
        $where['ORDER'] = ['id' => 'DESC'];
        $data['list'] = LotteryActivityModel::getRecords($where, [], false);
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
        ], false);
        if (empty($activityBaseData)) {
            return $detailData;
        }
        $detailData['base_data'] = $activityBaseData;
        //扩展数据
        $detailData['lottery_times_rule'] = LotteryFilterUserModel::getRecords($where, [
            "low_pay_amount",
            "high_pay_amount",
            "times"
        ], false);
        $detailData['win_prize_rule'] = LotteryAwardRuleModel::getRecords($where, [
            "low_pay_amount",
            "high_pay_amount",
            "award_level"
        ], false);
        $detailData['awards'] = LotteryAwardInfoModel::getRecords($where, [
            "name",
            "type",
            "award_detail",
            "level",
            "img_url",
            "weight",
            "num",
            "rest_num",
            "hit_times",
            "hit_times_type",
        ], false);
        return $detailData;
    }

    /**
     * 编辑状态
     * @param $opActivityId
     * @param $updateParamsData
     * @return bool
     */
    public static function updateEnableStatus($opActivityId, $updateParamsData): bool
    {
        //获取活动数据
        $activityData = LotteryActivityModel::getRecord(['op_activity_id' => $opActivityId]);
        if (empty($activityData)) {
            return false;
        }
        if ($activityData['status'] == $updateParamsData['status']) {
            return true;
        }
        return LotteryActivityModel::updateRecord($activityData['id'], $updateParamsData);
    }
}