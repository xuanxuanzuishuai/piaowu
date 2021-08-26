<?php
/**
 * erp 关于用户奖励信息
 * erp_user_event_task_award
 */

namespace App\Services;

use App\Models\Erp\ErpDictModel;
use App\Models\Erp\ErpUserEventTaskAwardModel;

class ErpUserEventTaskAwardService
{
    /** @var string 用户奖励 */
    const DICT_TYPE_USER_EVENT_TASK_AWARD_STATUS = 'user_event_task_award_status';

    /**
     * 通用红包审核 - 转介绍二期奖励列表
     * @param $params
     * @param $page
     * @param $count
     * @return array|array[]
     */
    public static function awardRedPackList($params, $page, $count)
    {
        $returnList = ErpUserEventTaskAwardModel::awardRedPackList($params, $page, $count);
        if (!empty($returnList['records'])) {
            foreach ($returnList['records'] as &$info) {
                $info['award_status_zh'] = ErpDictModel::getKeyValue(self::DICT_TYPE_USER_EVENT_TASK_AWARD_STATUS, $info['award_status']);
                $info['award'] = json_decode($info['award'], true);
            }
        }
        return $returnList;
    }
}
