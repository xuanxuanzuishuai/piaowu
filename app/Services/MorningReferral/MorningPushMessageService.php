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
use App\Libs\SimpleLogger;
use App\Models\MorningReferralStatisticsModel;
use App\Models\QrInfoOpCHModel;

class MorningPushMessageService
{
    /**
     * 获取目标用户的中文
     * @param $targetUserId
     * @return array|mixed|null
     */
    public static function getTargetUserDict($targetUserId)
    {
        return MorningDictConstants::get(MorningDictConstants::MORNING_PUSH_USER_GROUP, $targetUserId);
    }

    /**
     * 创建清晨转介绍关系
     * @param $data
     * @return false|void
     */
    public static function createReferral($data)
    {
        SimpleLogger::info("create morning referral params:", $data);
        if (empty($data)) {
            return false;
        }
        // 接收qr_id
        $qrId = $data['extra_params']['scene'] ?? '';
        if (empty($qrId)) {
            SimpleLogger::info("create morning referral scene is empty", $data);
            return false;
        }
        // 获取qr_id信息
        $qrInfo = QrInfoOpCHModel::getQrInfoById($qrId);
        if (empty($qrInfo)) {
            SimpleLogger::info("create morning referral qr info is not found", [$qrInfo]);
            return false;
        }
        // 获取用户信息
        $uuid = $data['uuid'] ?? '';
        if (empty($uuid)) {
            SimpleLogger::info("create morning referral uuid is not found", [$uuid]);
            return false;
        }
        // 获取学生状态
        $studentInfo = (new Morning())->getStudentList([$uuid])[0] ?? [];
        // 只有学生是注册用户,体验课用户可以创建转介绍关系
        if (empty($studentInfo['status']) || !in_array($studentInfo['status'], [Constants::MORNING_STUDENT_STATUS_REGISTE, Constants::MORNING_STUDENT_STATUS_TRAIL])) {
            SimpleLogger::info("create morning referral student status not error", [$studentInfo]);
            return false;
        }
        // 读取用户是否存在转介绍关系
        list($isHasReferral, $referralInfo) = self::getStudentReferral($uuid);
        if ($isHasReferral) {
            SimpleLogger::info("create morning referral student is has referral", [$isHasReferral, $referralInfo]);
            return false;
        }
        // 创建转介绍进度
        $createReferral = self::createStudentReferral($uuid);
        if (empty($createReferral)) {
            SimpleLogger::info("create morning referral create student referral fail", [$createReferral]);
            return false;
        }
        return true;
    }

    /**
     * 获取用户是否在各个业务线存在转介绍关系
     * @param $studentUuid
     * @return array
     */
    public static function getStudentReferral($studentUuid)
    {
        // 清晨是否有转介绍关系
        $morningHasReferral = MorningReferralStatisticsModel::getRecord();
        // 智能是否有转介绍关系
        // 真人是否有转介绍关系
        return [false, []];
    }

    /**
     * 创建学生转介绍关系
     * @param $studentUuid
     * @return bool
     */
    public static function createStudentReferral($studentUuid)
    {
        return true;
    }
}