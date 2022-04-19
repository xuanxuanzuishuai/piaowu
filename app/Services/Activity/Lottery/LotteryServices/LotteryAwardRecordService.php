<?php

namespace App\Services\Activity\Lottery\LotteryServices;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Models\LotteryAwardRecordModel;
use App\Services\UniqueIdGeneratorService\DeliverIdGeneratorService;

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
        if (!empty($activityInfo)) {
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
    public static function useLotteryTimes($opActivityId, $uuid)
    {
        $where = [
            'op_activity_id' => $opActivityId,
            'uuid'           => $uuid,
        ];
        return LotteryAwardRecordModel::getCount($where);
    }

    /**
     * 获取用户当天使用抽奖的次数
     * @param $opActivityId
     * @param $uuid
     * @return int|number
     */
    public static function getUserDayHitNum($opActivityId, $uuid)
    {
        $where = [
            'op_activity_id' => $opActivityId,
            'uuid'           => $uuid,
            'create_time[>]' => strtotime(date('Y-m-d')),
        ];
        return LotteryAwardRecordModel::getCount($where);
    }

    /**
     * 中奖记录入表
     * @param $params
     * @param $hitInfo
     * @return int|mixed|string|null
     */
    public static function addAwardRecord($params, $hitInfo)
    {
        $uniqueId = (new DeliverIdGeneratorService())->getDeliverId();
        $data = [
            'op_activity_id'  => $params['op_activity_id'],
            'uuid'            => $params['uuid'],
            'use_type'        => $params['use_type'],
            'award_id'        => $hitInfo['id'],
            'award_type'      => $hitInfo['type'],
            'unique_id'       => $uniqueId ?? 0,
            'shipping_status' => Constants::SHIPPING_STATUS_BEFORE,
            'create_time'     => time(),
        ];
        return LotteryAwardRecordModel::insertRecord($data);
    }

    /**
     * 更新中奖后活动表的相关数据
     * @param $params
     * @param $hitInfo
     * @return bool
     */
    public static function updateHitAwardInfo($params, $hitInfo)
    {
        $fields = [];
        if ($hitInfo['rest_num'] > 0) {
            $fields[] = 'rest_award_num';
        }

        if ($hitInfo['type'] != 1) {
            $fields[] = 'hit_times';
        }

        $times = self::useLotteryTimes($params['op_activity_id'], $params['uuid']);
        if ($times == 0) {
            $fields[] = 'join_num';
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        try {
            self::addAwardRecord($params, $hitInfo);
            LotteryActivityService::updateAfterHitInfo($params['op_activity_id'], $fields);
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
        }
        return true;
    }

}