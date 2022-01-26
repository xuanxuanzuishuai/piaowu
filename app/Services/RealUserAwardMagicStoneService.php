<?php
/**
 * 真人发放魔法石奖励
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RealDictConstants;
use App\Libs\SimpleLogger;
use App\Models\EmployeeModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\RealSharePosterPassAwardRuleModel;
use App\Models\RealUserAwardMagicStoneModel;
use App\Models\RealWeekActivityModel;
use App\Services\Queue\ErpStudentAccountTopic;
use App\Services\Queue\QueueService;

class RealUserAwardMagicStoneService
{
    /**
     * 真人 - 发放用户奖励(魔法石)
     * @param array $params
     * @return bool
     * @throws RunTimeException
     */
    public static function sendUserMagicStoneAward(array $params): bool
    {
        // 记录开始处理用户奖励信息，包括时间
        SimpleLogger::info('sendUserMagicStoneAward_params', [$params]);
        // 接收必要参数
        $appId = $params['app_id'] ?? 0;
        $activityId = $params['activity_id'] ?? 0;
        $studentId = $params['student_id'] ?? 0;
        $actStatus = $params['act_status'] ?? -1;
        $checkSuccessNumbers = $params['check_success_numbers'] ?? 1;
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
        // 根据成功通过审核次数获取应得奖励
        $passAwardInfo = RealSharePosterPassAwardRuleModel::getRecord(['activity_id' => $activityId, 'success_pass_num' => $checkSuccessNumbers]);
        if (empty($passAwardInfo)) {
            SimpleLogger::info('sendUserMagicStoneAward not found', [$params, $passAwardInfo]);
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
     * @return array
     */
    public static function getUserWeekActivityAwardList($userId, $userType, $page, $limit): array
    {
        [$totalCount, $awardList] = RealUserAwardMagicStoneModel::getList([
            'user_id'    => $userId,
            'user_type'  => $userType,
            'award_node' => Constants::WEEK_SHARE_POSTER_AWARD_NODE,
            'passes_num[>=]' => 1,
        ], $page, $limit, []);
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
        $awardInfo['other_data'] == 'null' && $awardInfo['other_data'] = [];    // other_data值是null字段重置为对应的空值
        $awardInfo['format_create_time'] = !empty($awardInfo['create_time']) ? date("Y-m-d H:i", $awardInfo['create_time']) : '';
        $awardInfo['format_update_time'] = !empty($awardInfo['update_time']) ? date("Y-m-d H:i", $awardInfo['update_time']) : '';
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
        $wxConfig = RealDictConstants::get(RealDictConstants::REAL_ACTIVITY_CONFIG, 'send_award_magic_stone_msg_id');
        QueueService::sendUserWxMsg(Constants::REAL_APP_ID, $studentId, $wxConfig, [
            'replace_params' => [
                'url' => RealDictConstants::get(RealDictConstants::REAL_REFERRAL_CONFIG, 'real_magic_stone_shop_url'),
            ],
        ]);
        // 返回结果
        return true;
    }
}
