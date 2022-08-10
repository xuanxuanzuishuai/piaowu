<?php
/**
 * 清晨推送消息
 * author: qingfeng.lian
 * date: 2022/6/24
 */

namespace App\Services\MorningReferral;

use App\Libs\Constants;
use App\Libs\Morning;
use App\Libs\MorningDictConstants;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpReferralUserRefereeModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\MorningReferralDetailModel;
use App\Models\MorningReferralStatisticsModel;
use App\Models\QrInfoOpCHModel;
use App\Models\StudentReferralStudentStatisticsModel;

class MorningPushMessageService
{
    const MORNING_PUSH_USER_ALL    = 1;    // 全部用户
    const MORNING_PUSH_USER_TRAIL  = 2;    // 清晨体验卡用户
    const MORNING_PUSH_USER_NORMAL = 3;    // 清晨年卡用户

    /**
     * 获取目标用户的中文
     * @param $targetUserId
     * @return array|mixed|null
     */
    public static function getTargetUserDict($targetUserId)
    {
        return MorningDictConstants::get(MorningDictConstants::MORNING_PUSH_USER_GROUP, $targetUserId);
    }
}