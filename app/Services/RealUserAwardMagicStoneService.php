<?php
/**
 * 真人发放魔法石奖励
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\RealDictConstants;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\EmployeeModel;
use App\Models\Erp\ErpDictModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\Erp\ReferralPosterModel;
use App\Models\RealSharePosterAwardModel;
use App\Models\RealSharePosterModel;
use App\Models\RealSharePosterPassAwardRuleModel;
use App\Models\RealUserAwardMagicStoneModel;
use App\Models\RealWeekActivityModel;
use App\Services\Queue\ErpStudentAccountTopic;
use App\Services\Queue\QueueService;

class RealUserAwardMagicStoneService
{
    /**
     * 真人 - 周周领奖审核通过发奖
     * @param $sharePosterId
     * @param $uuid
     * @param int $userType
     * @param array $sharePosterInfo
     * @return bool
     * @throws RunTimeException
     */
    private static function createStudentAward($sharePosterId, $uuid, array $sharePosterInfo = [], $userType = Constants::USER_TYPE_STUDENT)
    {
        $time       = time();
        $awardDelay = 0;
        // 获取分享截图审核 - 检查是否已经审核通过， 只有审核通过的才可以发奖
        if (empty($sharePosterInfo)) {
            $sharePosterInfo = RealSharePosterModel::getRecord(['id' => $sharePosterId]);
        }
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
            'reviewer_id'      => $sharePosterInfo['verify_user'] ?? EmployeeModel::SYSTEM_EMPLOYEE_ID,
            'review_reason'    => $sharePosterInfo['verify_reason'] ?? '',
            'review_time'      => $time,
            'remark'           => $sharePosterInfo['remark'] ?? '',
            'finish_task_uuid' => $uuid,
            'batch_id' => self::getBatchId(),
            'create_time'      => $time,
        ];
        $sharePosterAwardData = [
            'share_poster_id' => $sharePosterId,
            'award_id'        => 0,
            'award_type'      => Constants::ERP_ACCOUNT_NAME_MAGIC,
            'award_amount'    => $awardAmount,
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
            $uuid,
            $awardAmount,
            ErpStudentAccountTopic::UPLOAD_POSTER_ACTION,
            '截图审核通过',
            $time,
            $studentAwardMagicStoneData['batch_id']
        );

        // 发送消息
        QueueService::realSendPosterAwardMessage(["share_poster_id" => $sharePosterId]);

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
        foreach ($awardConfig as $_awardKey => $_awardNum) {
            if ($_awardKey > $lastAwardNum) {
                $lastAwardNum = $_awardKey;
            }
        }
        unset($_awardNum, $_awardKey);

        // 如果当前阶梯小于最后一个阶梯奖励，读取erp旧数据重新计算奖励
        if ($count <= $lastAwardNum) {
            $divisionTime = ErpDictModel::getKeyValue('student_poster_change_award', 'division_time');
            $erpCount     = ReferralPosterModel::getStudentSharePosterSuccessNum($studentId, $divisionTime);
            $count        += $erpCount;
        }
        return $awardConfig[$count] ?? $awardConfig['-1'];
    }

    /**
     * 真人 - 发放用户奖励(魔法石)
     * @param array $params
     * @return bool
     * @throws RunTimeException
     */
    public static function sendUserMagicStoneAward(array $params): bool
    {
        $oldRuleLastActivityId = RealDictConstants::get(RealDictConstants::REAL_ACTIVITY_CONFIG, 'old_rule_last_activity_id');
        // 记录开始处理用户奖励信息，包括时间
        SimpleLogger::info('sendUserMagicStoneAward_params', [$params]);
        // 接收必要参数  app_id,activity_id,student_id,act_status
        $appId = $params['app_id'] ?? 0;
        $activityId = $params['activity_id'] ?? 0;
        $studentId = $params['student_id'] ?? 0;
        $actStatus = $params['act_status'] ?? -1;
        /** 老活动的发放规则 */
        if ($activityId <= $oldRuleLastActivityId) {
            $sharePosterIds = $params['share_poster_ids'] ?? [];
            if (empty($sharePosterIds)) {
                throw new RunTimeException(['share_poster_ids_empty'], [$sharePosterIds]);
            }
            // 获取海报对应的学生id
            $sharePosterList = RealSharePosterModel::getRecords(['id' => $sharePosterIds]);
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
                $_sharePoserInfo = $sharePosterIdArr[$id] ?? [];
                $_studentId      = $_sharePoserInfo['student_id'] ?? 0;
                $uuid            = $studentUuidArr[$_studentId]['uuid'] ?? '';
                if (empty($_sharePoserInfo) || empty($_studentId) || empty($uuid)) {
                    // 如果student_id 或者 uuid 为空，则不发放积分 ，报警sentry
                    Util::errorCapture("sendUserMagicStoneAward_error", [$sharePosterIds, $_sharePoserInfo, $_studentId, $uuid]);
                    continue;
                }
                self::createStudentAward($id, $uuid, $_sharePoserInfo);
            }
            return true;
        }
        /** 最新发放规则 */

        // 参数不合规记录日志 停止发奖
        if ($appId != Constants::REAL_APP_ID || empty($activityId) || empty($studentId) || $actStatus == -1) {
            SimpleLogger::info('sendUserMagicStoneAward_params_error', [$params]);
            return false;
        }
        // 获取活动信息
        $activityInfo = RealWeekActivityModel::getRecord(['activity_id' => $activityId]);
        // 活动不存在停止发奖并记录日志
        if (empty($activityInfo)) {
            SimpleLogger::info('sendUserMagicStoneAward_activity_not_found', [$params, $activityInfo]);
            return false;
        }
        // 获取学生信息
        $studentInfo = ErpStudentModel::getRecord(['id' => $studentId]);
        // 指定业务线(真人)中学生是否存在
        if (empty($studentInfo)) {
            SimpleLogger::info('sendUserMagicStoneAward_student_not_found', [$params, $studentInfo]);
            return false;
        }
        // 获取用户活动中上传截图成功通过审核的次数
        $successSharePosterCount = RealSharePosterModel::getCount([
            'activity_id' => $activityId,
            'student_id' => $studentId,
            'type' => RealSharePosterModel::TYPE_WEEK_UPLOAD,
            'verify_status' => RealSharePosterModel::VERIFY_STATUS_QUALIFIED
        ]);
        // 根据成功通过审核次数获取应得奖励
        $passAwardInfo = RealSharePosterPassAwardRuleModel::getRecord(['activity_id' => $activityId, 'success_pass_num' => $successSharePosterCount]);
        if (empty($passAwardInfo)) {
            SimpleLogger::info('sendUserMagicStoneAward_RealSharePosterPassAwardRuleModel_not_found', [$params, $passAwardInfo]);
            return false;
        }
        // 发放奖励
        return self::realSaveStudentWeekAwardRecord($studentInfo, $activityInfo, $passAwardInfo);
    }

    /**
     * 生成batch_id
     * @return false|string
     */
    public static function getBatchId()
    {
        return substr(md5(uniqid()), 0, 6);
    }

    /**
     * 获取用户周周领奖活动奖励列表
     * @param $userId
     * @param $userType
     * @param $page
     * @param $limit
     * @param string[] $order
     * @return array
     */
    public static function getUserWeekActivityAwardList($userId, $userType, $page, $limit, array $order = ['id' => 'DESC']): array
    {
        [$totalCount, $awardList] = RealUserAwardMagicStoneModel::getList([
            'user_id'    => $userId,
            'user_type'  => $userType,
            'award_node' => Constants::WEEK_SHARE_POSTER_AWARD_NODE,
            'passes_num[>=]' => 1
        ], $page, $limit, $order);
        foreach ($awardList as &$item) {
            $item = self::formatAwardInfo($item);
        }
        unset($item);
        return ['total_count' => $totalCount, 'list' => $awardList];
    }

    /**
     * 格式化奖励信息
     * @param $awardInfo
     * @return mixed
     */
    public static function formatAwardInfo($awardInfo)
    {
        $awardInfo['passes_num'] = 0; // 审核通过次数
        $awardInfo['other_data'] == 'null' && $awardInfo['other_data'] = [];    // other_data值是null字段重置为对应的空值
        $awardInfo['format_create_time'] = !empty($awardInfo['create_time']) ? date("Y-m-d H:i", $awardInfo['create_time']) : '';
        $awardInfo['format_update_time'] = !empty($awardInfo['update_time']) ? date("Y-m-d H:i", $awardInfo['update_time']) : '';

        if (!empty($awardInfo['other_data'])) {
            $otherData = json_decode($awardInfo['other_data'], true);
            // 审核通过次数
            $awardInfo['passes_num'] = $otherData['passes_num'] ?? 0;
        }

        return $awardInfo;
    }

    /**
     * 真人 - 保存学生奖励信息记录
     * @param array $studentInfo 学生信息
     * @param array $activityInfo 活动信息
     * @param array $awardRuleInfo 奖励规则信息
     * @return bool
     * @throws RunTimeException
     */
    private static function realSaveStudentWeekAwardRecord(array $studentInfo, array $activityInfo = [], array $awardRuleInfo = []): bool
    {
        $time                       = time();
        $uuid = $studentInfo['uuid'] ?? '';
        $studentId = $studentInfo['id'] ?? 0;
        $activityId = $activityInfo['activity_id'] ?? 0;
        $awardAmount = $awardRuleInfo['award_amount'] ?? 0;
        $passesNum = $awardRuleInfo['success_pass_num'] ?? 0;
        if (empty($uuid) || empty($studentId) || empty($activityId) || empty($awardAmount)) {
            SimpleLogger::info('real_save_student_award_magic_stone_params_error', [$studentId, $activityInfo, $awardRuleInfo]);
            throw new RunTimeException(['real_save_student_award_magic_stone_params_error']);
        }
        $studentAwardMagicStoneData = [
            'user_id'          => $studentId,
            'uuid'             => $uuid,
            'user_type'        => Constants::USER_TYPE_STUDENT,
            'activity_id'      => $activityId,
            'award_status'     => RealUserAwardMagicStoneModel::STATUS_GIVE,
            'award_amount'     => $awardAmount,
            'award_node'       => Constants::WEEK_SHARE_POSTER_AWARD_NODE,
            'award_time'       => $time,
            'award_to'         => Constants::STUDENT_ID_INVITER,    // 上传截图奖励： 当做是给邀请人的奖励
            'award_delay'      => 0,
            'reviewer_id'      => $sharePosterInfo['verify_user'] ?? EmployeeModel::SYSTEM_EMPLOYEE_ID,
            'review_reason'    => $sharePosterInfo['verify_reason'] ?? '',
            'review_time'      => $time,
            'remark'           => $sharePosterInfo['remark'] ?? '',
            'finish_task_uuid' => $uuid,
            'batch_id' => self::getBatchId(),
            'create_time'      => $time,
            'passes_num'      => $passesNum,
            'other_data'      => json_encode([]),
        ];
        // 新增发放记录
        $awardId = RealUserAwardMagicStoneModel::insertRecord($studentAwardMagicStoneData);
        if ($awardId <= 0) {
            SimpleLogger::info('save_real_user_award_magic_stone', [$studentAwardMagicStoneData, $awardId]);
            throw new RunTimeException(['save_real_user_award_magic_stone']);
        }

        // 请求erp发放积分 - nsq通知erp积分入账
        QueueService::erpMagicStoneNormalCredited(
            $uuid,
            $awardAmount,
            ErpStudentAccountTopic::UPLOAD_POSTER_ACTION,
            '截图审核通过',
            $time,
            $studentAwardMagicStoneData['batch_id']
        );

        // 发送消息
        $wechatConfig = RealDictConstants::get(RealDictConstants::REAL_SHARE_POSTER_CONFIG, 'new-2');
        QueueService::sendUserWxMsg(Constants::REAL_APP_ID, $studentId, $wechatConfig, [
            'replace_params' => [
                'url' => RealDictConstants::get(RealDictConstants::REAL_REFERRAL_CONFIG, 'real_magic_stone_shop_url'),
            ],
        ]);
        // 返回结果
        return true;
    }
}
