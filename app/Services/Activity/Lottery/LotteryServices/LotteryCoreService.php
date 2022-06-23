<?php

namespace App\Services\Activity\Lottery\LotteryServices;

use App\Libs\SimpleLogger;
use App\Models\LotteryAwardRecordModel;

class LotteryCoreService
{
    /**
     * 计算中奖信息
     * @param $params
     * @return array|mixed
     */
    public static function LotteryCore($params)
    {
        SimpleLogger::info('LotteryCore start', $params);
        $params['award_info'] = LotteryAwardInfoService::getAwardInfo($params['op_activity_id']);

        //触发中奖上线直接返回兜底奖品
        $defaultAwardInfo = self::directReturnHitAward($params);
        if (!empty($defaultAwardInfo)) {
            SimpleLogger::info('LotteryCore direct return hit award', []);
            return $defaultAwardInfo;
        }

        //经过算法计算奖品
        if ($params['use_type'] == LotteryAwardRecordModel::USE_TYPE_FILTER) {
            SimpleLogger::info('LotteryCore consume filter times', []);
            $hitInfo = self::LotteryFilterRuleCore($params);
        } elseif ($params['use_type'] == LotteryAwardRecordModel::USE_TYPE_IMPORT) {
            SimpleLogger::info('LotteryCore consume import times', []);
            $hitInfo = self::LotteryImportCore($params);
        }

        return $hitInfo ?? [];
    }

    /**
     * 直接返回兜底奖品
     * @param $params
     * @return array|mixed
     */
    public static function directReturnHitAward($params)
    {
        if ($params['max_hit'] < 0 && $params['day_max_hit'] < 0) {
            return [];
        }
        //如果触发单个用户最大中奖次数，直接返回兜底奖品
        $userTotalHitNum = LotteryAwardRecordService::useLotteryTimes($params['op_activity_id'], $params['uuid'], 0, true);
        if ($params['max_hit'] > 0 && $userTotalHitNum >= $params['max_hit']) {
            $awardInfo = LotteryAwardInfoService::getAwardInfo($params['op_activity_id']);
            return array_pop($awardInfo);
        }

        //如果触发单个用户每日最大中奖次数，直接返回兜底奖品
        $userDayHitNum = LotteryAwardRecordService::getUserDayHitNum($params['op_activity_id'], $params['uuid']);
        if ($params['day_max_hit'] > 0 && $userDayHitNum >= $params['day_max_hit']) {
            $awardInfo = LotteryAwardInfoService::getAwardInfo($params['op_activity_id']);
            return array_pop($awardInfo);
        }
        return [];
    }

    /**
     * 根据规则计算中奖产品
     * @param $params
     * @return mixed
     */
    public static function LotteryFilterRuleCore($params)
    {
        //根据中奖规则确定课抽中奖品等级
        $readyAwardLevel = self::getReadyAwardLevel($params['op_activity_id'], $params['pay_amount']);

        //遍历符合条件的奖品
        $readyAwardInfo = self::removeAwardImport($params['award_info'], $params['time'], $readyAwardLevel);
        if (empty($readyAwardInfo)) {
            SimpleLogger::info('LotteryCore filter ready award empty', []);
            return array_pop($params['award_info']);
        }

        return self::core($readyAwardInfo);
    }

    /**
     * 计算导入用户的中奖产品
     * @param $params
     * @return mixed
     */
    public static function LotteryImportCore($params)
    {
        //遍历符合条件的奖品
        $readyAwardInfo = self::removeAwardImport($params['award_info'], $params['time'], []);
        if (empty($readyAwardInfo)) {
            SimpleLogger::info('LotteryCore import ready award empty', []);
            return array_pop($params['award_info']);
        }
        return self::core($readyAwardInfo);
    }

    /**
     * 按比例随机分配核心算法
     * @param $readyAwardList
     * @return mixed
     */
    public static function core($readyAwardList)
    {
        SimpleLogger::info('LotteryCore ready award', $readyAwardList);
        $length = 0;
        $readyAwardList = array_values($readyAwardList);
        for ($i = 0; $i < count($readyAwardList); $i++) {
            $length += $readyAwardList[$i]['weight'];
        }
        foreach ($readyAwardList as $key => $value) {
            $readyAwardList[$key]['weight_rate'] = number_format($value['weight'] / $length, 2);
        }
        SimpleLogger::info('LotteryCore ready award rate', $readyAwardList);
        for ($i = 0; $i < count($readyAwardList); $i++) {
            $random = rand(1, $length);
            if ($random <= $readyAwardList[$i]['weight']) {
                return $readyAwardList[$i];
            } else {
                $length -= $readyAwardList[$i]['weight'];
            }
        }
    }

    /**
     * 获取可抽中的奖品等级
     * @param $opActivityId
     * @param $payAmount
     * @return array|false|string[]
     */
    public static function getReadyAwardLevel($opActivityId, $payAmount)
    {
        $awardRule = LotteryAwardRuleService::getAwardRule($opActivityId);
        if (empty($awardRule)) {
            return [];
        }

        foreach ($awardRule as $rule) {
            if (($payAmount >= $rule['low_pay_amount']) && ($payAmount < $rule['high_pay_amount'])) {
                $res = explode(',', $rule['award_level']);
            }
        }

        return $res ?? [];
    }

    /**
     * 移除无效的产品
     * @param $awardInfo
     * @param $time
     * @param array $readyAwardLevel
     * @return array|mixed
     */
    public static function removeAwardImport($awardInfo, $time, $readyAwardLevel = [])
    {
        foreach ($awardInfo as $key => $award) {
            //移除没有库存的奖品
            if ($award['rest_num'] == 0) {
                unset($awardInfo[$key]);
                continue;
            }

            //移除不在可抽中时间的奖品
            $inTime = false;
            foreach ($award['hit_times'] as $ht) {
                if (($time >= $ht['start_time']) && ($time <= $ht['end_time'])) {
                    $inTime = true;
                }
            }
            if ($inTime == false) {
                unset($awardInfo[$key]);
                continue;
            }
        }

        if (empty($readyAwardLevel)) {
            return $awardInfo ?: [];
        }

        foreach ($awardInfo as $key => $award) {
            if (!in_array($award['level'], $readyAwardLevel)) {
                unset($awardInfo[$key]);
                continue;
            }
        }
        return $awardInfo ?: [];
    }
}