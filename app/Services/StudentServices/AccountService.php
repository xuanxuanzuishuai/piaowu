<?php

namespace App\Services\StudentServices;

use App\Libs\Constants;
use App\Libs\Erp;
use App\Models\Erp\ErpStudentAccountModel;
use App\Models\Erp\ErpUserEventTaskAwardGoldLeafModel;
use App\Models\StudentMessageReminderModel;

class AccountService
{
    /**
     * 获取学生账户信息统计数据
     * @param string $studentUuid
     * @return array
     */
    public static function getStudentAccountSurveyData(string $studentUuid): array
    {
        $erp = new Erp();
        $result = $erp->studentAccount($studentUuid);
        return self::formatStudentSurveyAccount($result['data'], $studentUuid);
    }

    /**
     * 格式化金叶子商城积分余额
     * @param array $data
     * @param string $uuid
     * @return array
     */
    private static function formatStudentSurveyAccount(array $data, string $uuid): array
    {

        //待发放金叶子总数
        $thawNum = ErpUserEventTaskAwardGoldLeafModel::getWaitSendGoldLeafBNum($uuid);
        //获取金叶子提醒消息数据
        $unreadMessageReminderCount = MessageReminderService::getUnreadMessageReminderCount($uuid,
            StudentMessageReminderModel::GOLD_LEAF_SHOP_REMINDER_TYPE);
        $goldLeafStatus = false;
        foreach ($data as &$account) {
            //金叶子
            if ($account['sub_type'] == ErpStudentAccountModel::SUB_TYPE_GOLD_LEAF) {
                $account['thaw_num'] = $thawNum;
                $account['unread_message_reminder_count'] = $unreadMessageReminderCount;
                $goldLeafStatus = true;
                break;
            }
        }
        //若无账户信息，但有待发放/取消金叶子明细，则初始化金叶子账户
        if (!$goldLeafStatus && !empty($thawResult)) {
            $data[] = [
                'account_name'                  => ErpStudentAccountModel::ACCOUNT_ASSETS_NAME_MAP[ErpStudentAccountModel::SUB_TYPE_GOLD_LEAF],
                'sub_type'                      => (string)ErpStudentAccountModel::SUB_TYPE_GOLD_LEAF,
                'app_id'                        => (string)Constants::SMART_APP_ID,
                'thaw_num'                      => $thawNum,
                'unread_message_reminder_count' => $unreadMessageReminderCount,
                'total_num'                     => 0,
            ];
        }
        return $data;
    }
}