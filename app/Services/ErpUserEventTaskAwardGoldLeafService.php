<?php


namespace App\Services;


use App\Libs\Util;
use App\Models\Dss\DssStudentModel;
use App\Models\EmployeeModel;
use App\Models\Erp\ErpEventTaskModel;
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
     * 获取待发放和发放失败金叶子积分明细列表
     * @param $params
     * @param $page
     * @param $limitNum
     * @param bool $isBackend 是否是后台调用
     * @return array
     */
    public static function getWaitingGoldLeafList($params, $page, $limitNum, $isBackend = false)
    {
        $limit = [
            ($page - 1) * $limitNum,
            $limitNum,
        ];
        $params['status'] = [
            ErpUserEventTaskAwardGoldLeafModel::STATUS_WAITING,
            ErpUserEventTaskAwardGoldLeafModel::STATUS_DISABLED,
        ];
        $list = ErpUserEventTaskAwardGoldLeafModel::getList($params, $limit);
        $returnList['total'] = $list['total'];
        $returnList['total_num'] = 0;

        // 如果自己是推荐人，显示被推荐人的手机号
        $buyerStudentMobileArr = [];

        foreach ($list['list'] as $item) {
            // 判断本条奖励是不是推荐人奖励 - 如果是推荐人获取被推荐人的手机号
            if ($item['to'] == ErpEventTaskModel::AWARD_TO_REFERRER) {
                if (!isset($buyerStudentMobileArr[$item['finish_task_uuid']])) {
                    $buyerStudentInfo = DssStudentModel::getRecord(['uuid' => $item['finish_task_uuid']], ['mobile']);
                    !empty($buyerStudentInfo) && $buyerStudentMobileArr[$item['finish_task_uuid']] = Util::hideUserMobile($buyerStudentInfo['mobile']);
                }
                $item['buyer_student_mobile'] = $buyerStudentMobileArr[$item['finish_task_uuid']];
            }
            // 等于作废不计算总数
            if ($item['status'] != ErpUserEventTaskAwardGoldLeafModel::STATUS_DISABLED) {
                $returnList['total_num'] += $item['award_num'];
            }
            $returnList['list'][] = self::formatGoldLeafInfo($item, $isBackend);
        }
        return $returnList;
    }

    public static function formatGoldLeafInfo($goldLeafInfo, $isBackend)
    {
        $goldLeafInfo['format_create_time'] = date("Y-m-d H:i:s", $goldLeafInfo['create_time']);
        $goldLeafInfo['mobile'] = Util::hideUserMobile($goldLeafInfo['mobile']);
        if (!empty($goldLeafInfo['reason']) && $goldLeafInfo['status'] == ErpUserEventTaskAwardGoldLeafModel::STATUS_DISABLED) {
            $goldLeafInfo['status_zh'] = ErpUserEventTaskAwardGoldLeafModel::REASON_RETURN_DICT[$goldLeafInfo['reason']] ?? '';
        }else {
            $goldLeafInfo['status_zh'] = self::STATUS_DICT[$goldLeafInfo['status']] ?? '';
        }

        if ($isBackend) {
            $goldLeafInfo['bill_id'] = "";  // erp后台需要的字段
            // 后期如果需要操作人，这里需要获取操作人的名称
            $goldLeafInfo['operator_name'] = $goldLeafInfo['operator_id'] == 0 ? EmployeeModel::SYSTEM_EMPLOYEE_NAME : '';  // erp后台需要的字段
        }
        return $goldLeafInfo;
    }
}