<?php


namespace App\Services;


use App\Libs\Util;
use App\Models\Erp\ErpStudentModel;
use App\Models\Erp\ErpUserEventTaskAwardGoldLeafModel;

class ErpUserEventTaskAwardGoldLeafService
{
    // 奖励发放状态对应的文字
    const STATUS_DICT = [
        ErpUserEventTaskAwardGoldLeafModel::STATUS_DISABLED => '不发放',
        ErpUserEventTaskAwardGoldLeafModel::STATUS_WAITING => '待发放',
        ErpUserEventTaskAwardGoldLeafModel::STATUS_REVIEWING => '审核中',
        ErpUserEventTaskAwardGoldLeafModel::STATUS_GIVE => '发放成功',
        ErpUserEventTaskAwardGoldLeafModel::STATUS_GIVE_ING => '发放中',
        ErpUserEventTaskAwardGoldLeafModel::STATUS_GIVE_FAIL => '发放失败',
    ];

    /**
     * 获取待发放金叶子积分明细列表
     * @param $params
     * @param $page
     * @param $limitNum
     * @return array
     */
    public static function getWaitingGoldLeafList($params, $page, $limitNum)
    {
        $limit = [
            ($page - 1) * $limitNum,
            $limitNum,
        ];
        $params['status'] = ErpUserEventTaskAwardGoldLeafModel::STATUS_WAITING;
        $list = ErpUserEventTaskAwardGoldLeafModel::getList($params, $limit);
        $returnList['total'] = $list['total'];
        $returnList['total_num'] = 0;
        foreach ($list['list'] as $item) {
            $returnList['total_num'] += $item['award_num'];
            $returnList['list'][] = self::formatGoldLeafInfo($item);
        }
        return $returnList;
    }

    public static function formatGoldLeafInfo($goldLeafInfo)
    {
        $goldLeafInfo['format_create_time'] = date("Y-m-d H:i:s", $goldLeafInfo['create_time']);
        $goldLeafInfo['mobile'] = Util::hideUserMobile($goldLeafInfo['mobile']);
        $goldLeafInfo['status_zh'] = self::STATUS_DICT[$goldLeafInfo['status']] ?? '';
        return $goldLeafInfo;
    }
}