<?php

namespace App\Services\Activity\Lottery\LotteryServices;

use App\Models\LotteryActivityModel;
use App\Models\LotteryAwardInfoModel;
use App\Models\LotteryAwardRuleModel;
use App\Models\LotteryFilterUserModel;
use App\Models\OperationActivityModel;

class LotteryActivityService
{
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