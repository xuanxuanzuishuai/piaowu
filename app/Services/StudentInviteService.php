<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/28
 * Time: 19:34
 */

namespace App\Services;


use App\Libs\SimpleLogger;
use App\Models\BillMapModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\ParamMapModel;
use App\Models\StudentInviteModel;
use App\Models\StudentReferralStudentStatisticsModel;

class StudentInviteService
{
    /**
     * 学生转介绍数据记录
     * @param $studentId
     * @param $qrTicket
     * @param $stage
     * @param $appId
     * @param $extParams
     * @param $parentBillId
     * @return array|bool
     */
    public static function studentInviteRecord($studentId, $stage, $appId, $qrTicket = '', $extParams = [], $parentBillId = '')
    {
        $time = time();
        $inviteRes = [];
        //根据不同的触发操作采取不同逻辑
        if ($stage == StudentReferralStudentStatisticsModel::STAGE_REGISTER) {
            //注册
            $inviteRes = self::registerLogic($studentId, $qrTicket, $appId, $time, $extParams);
        } elseif ($stage == StudentReferralStudentStatisticsModel::STAGE_TRIAL) {
            //购买体验卡
            $inviteRes = self::trailLogic($studentId, $parentBillId, $time);
        } elseif ($stage == StudentReferralStudentStatisticsModel::STAGE_FORMAL) {
            //购买年卡
            $inviteRes = self::normalLogic($studentId, $time);
        }
        return $inviteRes;
    }

    /**
     * 检测qr票据的身份归属
     * @param $qrTicket
     * @return bool|array
     */
    public static function checkQrTicketIdentity($qrTicket)
    {
        $identityData = ParamMapModel::getParamByQrTicket($qrTicket);
        if (empty($identityData)) {
            $identityData = DssUserQrTicketModel::getRecord(['qr_ticket' => $qrTicket]);
        }
        //数据不存在
        if (empty($identityData)) {
            SimpleLogger::info('not find ticket user', ['ticket' => $qrTicket]);
            return [];
        }
        return ['invite_user_type' => $identityData['type'], 'invite_user_id' => $identityData['user_id']];
    }


    /**
     * 注册转介绍关系记录
     * @param $studentId
     * @param $qrTicket
     * @param $appId
     * @param $time
     * @param $extParams
     * @return array
     */
    private static function registerLogic($studentId, $qrTicket, $appId, $time, $extParams)
    {
        $res = [
            'record_res' => false,
        ];
        //注册:通过qr_ticket区分推荐人的身份：代理商 学生
        $qrTicketIdentityData = self::checkQrTicketIdentity($qrTicket);
        if (empty($qrTicketIdentityData)) {
            return $res;
        }
        $res['invite_user_type'] = $qrTicketIdentityData['invite_user_type'];
        $res['invite_user_id'] = $qrTicketIdentityData['invite_user_id'];
        //是否存在注册转介绍关系
        $registerReferralInfo = StudentInviteModel::getRecord(['student_id' => $studentId, 'app_id' => $appId], ['student_id']);
        if (!empty($registerReferralInfo)) {
            SimpleLogger::info('has register referral relation', ['register_referral_info' => $registerReferralInfo]);
            return $res;
        }
        //记录数据
        $inviteId = StudentInviteModel::insertRecord(
            [
                'student_id' => $studentId,
                'referee_id' => $qrTicketIdentityData['invite_user_id'],
                'referee_type' => $qrTicketIdentityData['invite_user_type'],
                'create_time' => $time,
                'referee_employee_id' => $extParams['e'] ?? 0,
                'activity_id' => $extParams['a'] ?? 0,
                'app_id' => $appId
            ]
        );
        if (empty($inviteId)) {
            SimpleLogger::info('register referral invite record fail', []);
            return $res;
        }
        $res['record_res'] = true;
        return $res;
    }

    /**
     * 购买体验卡绑定关系以及节点进度数据记录
     * @param $studentId
     * @param $parentBillId
     * @param $time
     * @return bool
     */
    private static function trailLogic($studentId, $parentBillId, $time)
    {
        //通过订单ID获取成单人的映射关系：已存在学生转介绍绑定关系的老数据跳过检测
        $bindReferralInfo = StudentReferralStudentStatisticsModel::getRecord(['student_id' => $studentId], ['activity_id', 'referee_employee_id', 'referee_id']);
        if (empty($bindReferralInfo)) {
            //新绑定逻辑条件检测
            $qrTicketIdentityData = BillMapModel::paramMapDataByBillId($parentBillId, $studentId);
        } else {
            $qrTicketIdentityData = [
                'a' => $bindReferralInfo['activity_id'],
                'e' => $bindReferralInfo['referee_employee_id'],
                'type' => ParamMapModel::TYPE_STUDENT,
                'user_id' => $bindReferralInfo['referee_id']];
        }
        if (empty($qrTicketIdentityData)) {
            return false;
        }
        if ($qrTicketIdentityData['type'] == ParamMapModel::TYPE_STUDENT) {
            //成单人身份是学生
            return StudentReferralStudentService::trailReferralRecord($studentId, $qrTicketIdentityData, $time);
        } elseif ($qrTicketIdentityData['type'] == ParamMapModel::TYPE_AGENT) {
            //成单人身份是代理商，此处不处理，执行代理商关系绑定以及奖励逻辑
            return true;
        } else {
            return false;
        }
    }

    /**
     * 购买年卡绑定关系以及节点进度数据记录
     * @param $studentId
     * @param $time
     * @return bool
     */
    private static function normalLogic($studentId, $time)
    {
        return StudentReferralStudentService::normalReferralRecord($studentId, $time);
    }
}