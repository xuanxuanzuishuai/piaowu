<?php

namespace App\Services\Activity\Lottery\LotteryServices;

use App\Models\LotteryAwardRecordModel;
use function Script\sendMail;

class LotteryCoreService
{
    public static function LotteryCore($params)
    {
        if ($params['use_type'] == LotteryAwardRecordModel::USE_TYPE_FILTER){
            $hitInfo = self::LotteryFilterRuleCore($params);
        }elseif ($params['use_type'] == LotteryAwardRecordModel::USE_TYPE_IMPORT){
            $hitInfo = self::LotteryImportCore($params);
        }

        return $hitInfo ?? [];
    }


    public static function LotteryFilterRuleCore($params)
    {
        //根据中奖规则确定课抽中奖品等级
        $readyAwardLevel = self::getReadyAwardLevel($params['op_activity_id'],$params['pay_amount']);


    }

    public static function LotteryImportCore($params)
    {

    }

    /**
     * 按比例随机分配核心算法
     * @param $readyAwardList
     * @return mixed
     */
    public static function core($readyAwardList){
        $length = 0;
        for ($i = 0; $i < count($readyAwardList); $i++) {
            $length += $readyAwardList[$i]['rate'];
        }

        for ($i = 0; $i < count($readyAwardList); $i++) {
            $random = rand(1, $length);
            if ($random <= $readyAwardList[$i]) {
                return $readyAwardList[$i];
            } else {
                $length -= $readyAwardList[$i]['rate'];
            }
        }
    }

    public static function getReadyAwardLevel($opActivityId,$payAmount)
    {
        $awardRule = LotteryAwardRuleService::getAwardRule($opActivityId);


    }

}