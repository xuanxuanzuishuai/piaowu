<?php

namespace App\Services\Activity\Lottery\LotteryServices;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Models\LotteryAwardInfoModel;

class LotteryAwardInfoService
{
    /**
     * 获取奖品信息
     * @param $opActivityId
     * @param array $fields
     * @return array|mixed
     */
    public static function getAwardInfo($opActivityId,array $fields = []):array
    {
        $where = [
            'op_activity_id' => $opActivityId,
            'status'         => Constants::STATUS_TRUE
        ];
        $activityInfo = LotteryAwardInfoModel::getRecords($where,$fields);
        if (empty($activityInfo)) {
            return [];
        }

        foreach ($activityInfo as $key => $value) {
            if (!empty($value['award_detail'])){
                $activityInfo[$key]['award_detail'] = json_decode($value['award_detail'], true);
            }
            if (!empty($value['hit_times'])){
                $activityInfo[$key]['hit_times'] = json_decode($value['hit_times'], true);
            }
            if (!empty($value['img_url'])){
                $activityInfo[$key]['img_url'] = AliOSS::replaceCdnDomainForDss($value['img_url']);
            }
        }
        return $activityInfo;
    }

    /**
     * 扣减中奖商品的库存
     * @param $hitInfo
     * @return int|null
     */
    public static function decreaseRestNum($hitInfo)
    {
        if ($hitInfo['num'] < 0) {
            return 1;
        }
        $date = [
            'rest_num[-]' => 1
        ];
        $where = [
            'id'       => $hitInfo['id'],
            'rest_num' => $hitInfo['rest_num'],
        ];
        return LotteryAwardInfoModel::batchUpdateRecord($date, $where);
    }
}