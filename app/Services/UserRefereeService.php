<?php
namespace App\Services;

use App\Libs\Constants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\StudentInviteModel;


class UserRefereeService
{
    const EVENT_TYPE_REGISTER = 'event_type_register';
    const EVENT_TYPE_BUY = 'event_type_buy';
    /** 任务状态 */
    const EVENT_TASK_STATUS_COMPLETE = 2;

    /**
     * 转介绍奖励入口
     * @param $appId
     * @param $eventType
     * @param $params
     * @throws RunTimeException
     */
    public static function  refereeAwardDeal($appId, $eventType, $params)
    {
        //注册时间处理
        if ($eventType == self::EVENT_TYPE_REGISTER) {
            self::registerDeal($params['student_id'] ?? NULL, $params['qr_ticket'] ?? NULL, $appId, $params['employee_id'] ?? NULL, $params['activity_id'] ?? NULL);
        }
        //付费事件处理
        if ($eventType == self::EVENT_TYPE_BUY) {
            self::buyDeal($params['pre_buy_student_info'] ?? NULL, $params['package_info'] ?? NULL, $appId);
        }
    }

    /**
     * 注册事件处理
     * @param $studentId
     * @param $qrTicket
     * @param $appId
     * @param null $employeeId
     * @param null $activityId
     * @throws RunTimeException
     */
    public static function registerDeal($studentId, $qrTicket, $appId, $employeeId = NULL, $activityId = NULL)
    {
        if (empty($studentId) || empty($qrTicket) || empty($appId)) {
            throw new RunTimeException(['param_lay']);
        }
        //绑定转介绍关系
        $res = self::bindRegister($studentId, $qrTicket, $appId, $employeeId, $activityId);
        if (empty($res)) {
            return;
        }
        //注册奖励发放
        self::registerAwardDeal($studentId, $appId);
    }

    /**
     * 注册奖励发放
     * @param $studentId
     * @param $appId
     */
    public static function registerAwardDeal($studentId, $appId)
    {
        if ($appId == Constants::SMART_APP_ID) {
            //学生信息
            $studentInfo = DssStudentModel::getRecord(['id' => $studentId]);
            // 转介绍二期，注册不再给奖励，只存占位数据
            (new Erp())->updateTask($studentInfo['uuid'],
                RefereeAwardService::getDssRegisterTaskId(),
                self::EVENT_TASK_STATUS_COMPLETE);
        }
    }

    /**
     * 转介绍关系建立
     * @param $studentId
     * @param $qrTicket
     * @param $appId
     * @param null $employeeId
     * @param null $activityId
     * @return bool
     */
    public static function bindRegister($studentId, $qrTicket, $appId, $employeeId = NULL, $activityId = NULL)
    {
        if ($appId == Constants::SMART_APP_ID) {
            //推荐人信息
            $refereeInfo = DssUserQrTicketModel::getRecord(['qr_ticket' => $qrTicket]);
            //是否已经绑定
            $bindInfo = StudentInviteModel::getRecord(['student_id' => $studentId, 'app_id' => $appId]);
            if (!empty($bindInfo)) {
                SimpleLogger::info('has bind referee relation', ['bind_info' => $bindInfo]);
                return false;
            }
            $res = StudentInviteModel::insertRecord(
                [
                    'student_id' => $studentId,
                    'referee_id' => $refereeInfo['user_id'],
                    'referee_type' => $refereeInfo['type'],
                    'create_time' => time(),
                    'referee_employee_id' => $employeeId,
                    'activity_id' => $activityId,
                    'app_id' => $appId
                ]
            );
            if (empty($res)) {
                SimpleLogger::info('bind relate fail', ['bind_info' => $bindInfo]);
                return  false;
            }
            return true;
        }
    }

    /**
     * 付费事件处理
     * @param $buyPreStudentInfo
     * @param $packageInfo
     * @param $appId
     */
    public static function buyDeal($buyPreStudentInfo, $packageInfo, $appId)
    {
        if ($appId == Constants::SMART_APP_ID) {
            self::dssBuyDeal($buyPreStudentInfo, $packageInfo);
        }
    }

    /**
     * dss付费事件处理
     * @param $buyPreStudentInfo
     * @param $packageInfo
     */
    public static function dssBuyDeal($buyPreStudentInfo, $packageInfo)
    {
        // 是否发奖
        if (RefereeAwardService::dssShouldCompleteEventTask($buyPreStudentInfo, $packageInfo)) {
            self::dssCompleteEventTask($buyPreStudentInfo['id'], $packageInfo['package_type'], $packageInfo['trial_type'], $packageInfo['app_id']);
        }
    }


    /**
     * 完成转介绍任务
     * @param $studentId
     * @param $packageType
     * @param $trialType
     * @param $appId
     */
    public static function dssCompleteEventTask($studentId, $packageType, $trialType, $appId)
    {
        //当前用户的推荐人
        $refereeRelation = StudentInviteModel::getRecord(
            [
                'student_id' => $studentId,
                'app_id' => Constants::SMART_APP_ID
            ]
        );
        if (empty($refereeRelation)) {
            SimpleLogger::info('not referee relate', ['student_id' => $studentId, 'app_id' => Constants::SMART_APP_ID]);
            return;
        }
        $refereeInfo = DssStudentModel::getRecord(['id' => $refereeRelation['referee_id']]);

        $refTaskId = null;

        if ($packageType == DssPackageExtModel::PACKAGE_TYPE_TRIAL) {
            if (in_array($trialType, [DssPackageExtModel::TRIAL_TYPE_49, DssPackageExtModel::TRIAL_TYPE_9])) {
                // 购买49,9.9体验包完成转介绍任务
                if (in_array($refereeInfo['has_review_course'], [DssStudentModel::REVIEW_COURSE_NO, DssStudentModel::REVIEW_COURSE_49])) {
                    // 若用户（推荐人）当前阶段为“已注册”或“付费体验课”
                    $refTaskId = RefereeAwardService::getDssTrailPayTaskId();
                } elseif (in_array($refereeInfo['has_review_course'], [DssStudentModel::REVIEW_COURSE_1980])) {
                    // 若用户（推荐人）当前阶段为“付费正式课”
                    $refTaskId = RefereeAwardService::getDssTrailPayTaskId(1);
                }
            }
        } elseif ($packageType == DssPackageExtModel::PACKAGE_TYPE_NORMAL) {
            if ($appId == DssPackageExtModel::APP_AI) {
                // 购买正式包完成转介绍任务
                if (in_array($refereeInfo['has_review_course'], [DssStudentModel::REVIEW_COURSE_NO, DssStudentModel::REVIEW_COURSE_49])) {
                    // 若用户（推荐人）当前阶段为“已注册”或“付费体验课”
                    $refTaskId = RefereeAwardService::getDssYearPayTaskId();
                } elseif (in_array($refereeInfo['has_review_course'], [DssStudentModel::REVIEW_COURSE_1980])) {
                    // 若用户（推荐人）当前阶段为“付费正式课”
                    $refTaskId = RefereeAwardService::getDssYearPayTaskId(1);
                }
            }
        }
        $studentInfo = DssStudentModel::getById($studentId);
        if (!empty($refTaskId)) {
            $erp = new Erp();
            $erp->updateTask($studentInfo['uuid'], $refTaskId, self::EVENT_TASK_STATUS_COMPLETE);
        }
    }
}