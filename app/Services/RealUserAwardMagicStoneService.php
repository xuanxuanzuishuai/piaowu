<?php
/**
 * 真人发放魔法石奖励
 */

namespace App\Services;

use App\Controllers\OrgWeb\RealSharePoster;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\RealDictConstants;
use App\Libs\SentryClient;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Erp\ErpDictModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\Erp\ReferralPosterModel;
use App\Models\RealSharePosterAwardModel;
use App\Models\RealSharePosterModel;
use App\Models\RealUserAwardMagicStoneModel;
use App\Services\Queue\ErpStudentAccountTopic;
use App\Services\Queue\QueueService;

class RealUserAwardMagicStoneService
{
    /**
     * 真人 - 周周领奖审核通过发奖
     * @param $sharePosterId
     * @param $uuid
     * @param int $userType
     * @return bool
     * @throws RunTimeException
     */
    private static function createStudentAward($sharePosterId, $uuid, $userType = Constants::USER_TYPE_STUDENT)
    {
        $time       = time();
        $awardDelay = 0;
        // 获取分享截图审核 - 检查是否已经审核通过， 只有审核通过的才可以发奖
        $sharePosterInfo = RealSharePosterModel::getRecord(['id' => $sharePosterId]);
        if (empty($sharePosterInfo) || $sharePosterInfo['verify_status'] != RealSharePosterModel::VERIFY_STATUS_QUALIFIED) {
            SimpleLogger::info(['share_poser_status_error'], [$sharePosterId, $uuid, $userType]);
            throw new RunTimeException(['share_poser_status_error']);
        }
        // 计算阶梯 - 这里不足需要去erp旧表确认是否有老数据
        $awardAmount = self::getStudentSharePosterAwardAmount($sharePosterInfo['student_id'], $sharePosterId);

        if (empty($uuid)) {
            $studentInfo = ErpStudentModel::getRecord(['id' => $sharePosterInfo['student_id']], ['uuid']);
            $uuid        = $studentInfo['uuid'] ?? '';
        }
        $studentAwardMagicStoneData = [
            'user_id'          => $sharePosterInfo['student_id'],
            'uuid'             => $uuid,
            'user_type'        => $userType,
            'activity_id'      => $sharePosterInfo['activity_id'],
            'award_status'     => RealUserAwardMagicStoneModel::STATUS_GIVE,
            'award_amount'     => $awardAmount,
            'award_node'       => Constants::WEEK_SHARE_POSTER_AWARD_NODE,
            'award_time'       => $time + $awardDelay,
            'award_to'         => Constants::STUDENT_ID_INVITER,    // 上传截图奖励： 当做是给邀请人的奖励
            'award_delay'      => $awardDelay,
            'reviewer_id'      => $sharePosterInfo['verify_user'],
            'review_reason'    => $sharePosterInfo['verify_reason'] ?? '',
            'review_time'      => $time,
            'remark'           => $sharePosterInfo['remark'] ?? '',
            'finish_task_uuid' => $uuid,
            'create_time'      => $time,
        ];
        $sharePosterAwardData       = [
            'share_poster_id' => $sharePosterId,
            'award_id'        => 0,
            'award_type'      => Constants::ERP_ACCOUNT_NAME_MAGIC,
            'award_num'       => $awardAmount,
            'create_time'     => $time,
        ];

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        // 新增发放记录
        $awardId = RealUserAwardMagicStoneModel::insertRecord($studentAwardMagicStoneData);
        if ($awardId <= 0) {
            $db->rollBack();
            SimpleLogger::info(['save_real_user_award_magic_stone'], [$sharePosterId, $uuid, $userType, $studentAwardMagicStoneData]);
            throw new RunTimeException(['save_real_user_award_magic_stone']);
        }
        // 新增分享截图审核发放记录
        $sharePosterAwardData['award_id'] = $awardId;
        $sharePosterAwardId               = RealSharePosterAwardModel::insertRecord($sharePosterAwardData);
        if ($sharePosterAwardId <= 0) {
            $db->rollBack();
            SimpleLogger::info(['save_real_share_poster_award'], [$sharePosterId, $uuid, $userType, $sharePosterAwardData]);
            throw new RunTimeException(['save_real_share_poster_award']);
        }
        $db->commit();

        // 请求erp发放积分 - nsq通知erp积分入账
        QueueService::erpMagicStoneNormalCredited(
            $sharePosterInfo['student_id'],
            ErpStudentAccountTopic::UPLOAD_POSTER_ACTION,
            $awardAmount,
            '截图审核通过',
            $time
        );

        // 发送消息

        // 返回结果
        return true;
    }

    /**
     * 真人 - 获取学生通过分享截图审核应该获取的奖励魔法石
     * @param $studentId
     * @param $poserId
     * @return mixed
     */
    public static function getStudentSharePosterAwardAmount($studentId, $poserId)
    {
        //计算当前真正应该获得的奖励
        $where       = [
            'id[!]'         => $poserId,
            'student_id'    => $studentId,
            'type'          => RealSharePosterModel::TYPE_WEEK_UPLOAD,
            'verify_status' => RealSharePosterModel::VERIFY_STATUS_QUALIFIED,
        ];
        $count       = RealSharePosterModel::getCount($where);
        $awardConfig = DictConstants::getSet(RealDictConstants::REAL_SHARE_POSTER_AWARD_RULE);
        // 获取最后一次奖励阶梯
        $lastAwardNum = 0;
        foreach ($awardConfig as $_awardNum) {
            if ($_awardNum > $lastAwardNum) {
                $lastAwardNum = $_awardNum;
            }
        }
        unset($_awardNum);

        // 如果当前阶梯小于最后一个阶梯奖励，读取erp旧数据重新计算奖励
        if ($count < $lastAwardNum) {
            $divisionTime = ErpDictModel::getKeyValue('student_poster_change_award', 'division_time');
            $erpCount     = ReferralPosterModel::getStudentSharePosterSuccessNum($studentId, $divisionTime, $poserId);
            $count        += $erpCount;
        }
        return $awardConfig[$count] ?? $awardConfig['-1'];
    }

    /**
     * 真人 - 发放用户奖励(魔法石)
     * @param $sharePosterIds
     * @return bool
     * @throws RunTimeException
     */
    public static function sendUserMagicStoneAward($sharePosterIds)
    {
        // 获取海报对应的学生id
        $sharePosterList = RealSharePosterModel::getRecords(['id' => $sharePosterIds], ['student_id', 'id']);
        if (empty($sharePosterList)) {
            throw new RunTimeException(['poster_not_found'], [$sharePosterIds]);
        }
        // 获取学生uuid - 用户发放魔法石
        $studentList = ErpStudentModel::getRecords(['id' => array_column($sharePosterList, 'student_id')], ['uuid', 'id']);
        if (empty($studentList)) {
            throw new RunTimeException(['student_not_found'], [$sharePosterIds]);
        }
        $studentUuidArr   = array_column($studentList, null, 'id');
        $sharePosterIdArr = array_column($sharePosterList, null, 'id');
        // 发放奖励
        foreach ($sharePosterIds as $id) {
            $_studentId = $sharePosterIdArr[$id] ?? 0;
            $uuid       = $studentUuidArr[$_studentId]['uuid'] ?? '';
            if (empty($_studentId) || empty($uuid)) {
                // 如果student_id 或者 uuid 为空，则不发放积分 ，报警sentry
                Util::errorCapture("sendUserMagicStoneAward_error", [$sharePosterIds, $_studentId, $uuid]);
                continue;
            }
            self::createStudentAward($id, $uuid);
        }
        return true;
    }
}
