<?php
namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Models\AgentAwardDetailModel;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\ParamMapModel;
use App\Models\StudentInviteModel;
use App\Services\Queue\QueueService;


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
        //注册事件处理
        if ($eventType == self::EVENT_TYPE_REGISTER) {
            return;
            //不再处理，有延迟，需要同步创建
            //self::registerDeal($params['student_id'] ?? NULL, $params['qr_ticket'] ?? NULL, $appId, $params['employee_id'] ?? NULL, $params['activity_id'] ?? NULL);
        }
        //付费事件处理
        if ($eventType == self::EVENT_TYPE_BUY) {
            self::buyDeal(
                $params['pre_buy_student_info'] ?? [],
                $params['package_info'] ?? [],
                $appId,
                $params['parent_bill_id'] ?? 0
                );
        }
    }

    /**
     * 注册事件处理
     * @param $studentId
     * @param $uuid
     * @param $qrTicket
     * @param $appId
     * @param array $extParams
     * @throws RunTimeException
     */
    public static function registerDeal($studentId, $uuid, $qrTicket, $appId, $extParams = [])
    {
        if (empty($studentId) || empty($qrTicket) || empty($appId)) {
            throw new RunTimeException(['param_lay']);
        }
        //绑定转介绍关系
        $res = self::bindRegister($uuid, $studentId, $qrTicket, $appId, $extParams);
        if (empty($res)) {
            return;
        }
    }

    /**
     * 注册奖励发放
     * @param $uuid
     * @param $appId
     * @throws RunTimeException
     */
    public static function registerAwardDeal($uuid, $appId)
    {
        if ($appId == Constants::SMART_APP_ID) {
            //学生信息
            // 转介绍二期，注册不再给奖励，只存占位数据
            (new Erp())->updateTask($uuid,
                RefereeAwardService::getDssRegisterTaskId(),
                self::EVENT_TASK_STATUS_COMPLETE);
        }
    }

    /**
     * 检测qr票据的身份归属
     * @param $qrTicket
     * @return bool|array
     */
    private static function checkQrTicketIdentity($qrTicket)
    {
        //推荐人信息
        $qrTicketData = [
            'identity' => 0,
            'referee_info' => []
        ];
        $identityData = ParamMapModel::checkAgentValidStatusByQr($qrTicket);
        if (empty($identityData)) {
            $identityData = DssUserQrTicketModel::getRecord(['qr_ticket' => $qrTicket]);
        }
        //数据不存在
        if (empty($identityData)) {
            SimpleLogger::info('not find ticket user', ['ticket' => $qrTicket]);
            return $qrTicketData;
        }
        $qrTicketData['referee_info'] = $identityData;
        if ($identityData['type'] == ParamMapModel::TYPE_AGENT) {
            //代理商
            $qrTicketData['identity'] = ParamMapModel::TYPE_AGENT;
        } elseif ($identityData['type'] == ParamMapModel::TYPE_STUDENT) {
            //智能陪练学生
            $qrTicketData['identity'] = ParamMapModel::TYPE_STUDENT;
        }
        return $qrTicketData;
    }

    /**
     * 转介绍关系建立
     * @param $uuid
     * @param $studentId
     * @param $qrTicket
     * @param $appId
     * @param array $extParams
     * @return bool
     * @throws RunTimeException
     */
    public static function bindRegister($uuid, $studentId, $qrTicket, $appId, $extParams = [])
    {
        $time = time();
        if ($appId == Constants::SMART_APP_ID) {
            //是否已经绑定
            $bindInfo = StudentInviteModel::getRecord(['student_id' => $studentId, 'app_id' => $appId]);
            if (!empty($bindInfo)) {
                SimpleLogger::info('has bind referee relation', ['bind_info' => $bindInfo]);
                return false;
            }
            //通过qr_ticket区分转介绍人的身份：代理商 学生
            $qrTicketData = self::checkQrTicketIdentity($qrTicket);
            if (empty($qrTicketData['identity'])) {
                return false;
            }
            //绑定学生与转介绍的关系
            $res = StudentInviteModel::insertRecord(
                [
                    'student_id' => $studentId,
                    'referee_id' => $qrTicketData['referee_info']['user_id'],
                    'referee_type' => $qrTicketData['referee_info']['type'],
                    'create_time' => $time,
                    'referee_employee_id' => $extParams['e'] ?? 0,
                    'activity_id' => $extParams['a'] ?? 0,
                    'app_id' => $appId
                ]
            );
            if (empty($res)) {
                SimpleLogger::info('bind relate fail', ['bind_info' => $bindInfo]);
                return  false;
            }
            if ($qrTicketData['identity'] == ParamMapModel::TYPE_AGENT) {
                //发放奖励
                AgentAwardService::agentReferralBillAward($qrTicketData['referee_info']['user_id'], ['id' => $studentId], AgentAwardDetailModel::AWARD_ACTION_TYPE_REGISTER);
            } else {
                //注册奖励发放
                self::registerAwardDeal($uuid, $appId);
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * 付费事件处理
     * @param $buyPreStudentInfo
     * @param $packageInfo
     * @param $appId
     * @param int $parentBillId 主订单ID
     * @return bool
     * @throws RunTimeException
     */
    public static function buyDeal($buyPreStudentInfo, $packageInfo, $appId, $parentBillId)
    {
        if ($appId == Constants::SMART_APP_ID) {
            // 查询代理商绑定关系
            $agentId = AgentService::checkBillIsAgentReferral($buyPreStudentInfo['id'], $parentBillId, $packageInfo['package_type']);
            if ($agentId) {
                //代理商分享购买
                return AgentAwardService::agentReferralBillAward($agentId, $buyPreStudentInfo, $packageInfo['package_type'], $packageInfo, $parentBillId);
            }
            self::dssBuyDeal($buyPreStudentInfo, $packageInfo);
        }
        return true;
    }

    /**
     * dss付费事件处理
     * @param $buyPreStudentInfo
     * @param $packageInfo
     * @throws RunTimeException
     */
    public static function dssBuyDeal($buyPreStudentInfo, $packageInfo)
    {
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
     * @throws RunTimeException
     */
    public static function dssCompleteEventTask($studentId, $packageType, $trialType, $appId)
    {
        //当前用户的推荐人
        $refereeRelation = StudentInviteModel::getRecord(
            [
                'student_id' => $studentId,
                'app_id' => Constants::SMART_APP_ID,
                'referee_type' => StudentInviteModel::REFEREE_TYPE_STUDENT
            ]
        );
        if (empty($refereeRelation)) {
            SimpleLogger::info('not referee relate', ['student_id' => $studentId, 'app_id' => Constants::SMART_APP_ID]);
            return;
        }
        $refereeInfo = DssStudentModel::getRecord(['id' => $refereeRelation['referee_id']]);
        $refereeInfo['student_id'] = $studentId;
        $refereeInfo['first_pay_normal_info'] = DssGiftCodeModel::getUserFirstPayNormalInfo($refereeRelation['referee_id']);

        $refTaskId = self::getTaskIdByType($packageType, $trialType, $refereeInfo, $appId);

        $studentInfo = DssStudentModel::getById($studentId);
        if (!empty($refTaskId)) {
            $erp = new Erp();
            $data = $erp->updateTask($studentInfo['uuid'], $refTaskId, self::EVENT_TASK_STATUS_COMPLETE);
            if (!empty($data['user_award_ids'])) {
                foreach ($data['user_award_ids'] as $awardId) {
                    QueueService::sendRedPack([['id' => $awardId]]);
                }
            }
        }
    }

    /**
     * 根据购买类型获取对应任务ID
     * @param $packageType
     * @param $trialType
     * @param $refereeInfo
     * @param $appId
     * @return int|string
     */
    public static function getTaskIdByType($packageType, $trialType, $refereeInfo, $appId)
    {
        // 推荐人当前状态：非付费正式课：
        if ($refereeInfo['has_review_course'] != DssStudentModel::REVIEW_COURSE_1980) {
            if ($packageType == DssPackageExtModel::PACKAGE_TYPE_TRIAL
                && in_array($trialType, [DssPackageExtModel::TRIAL_TYPE_49, DssPackageExtModel::TRIAL_TYPE_9])) {
                // DSSCRM-1841: 奖励推荐人1元：
                return RefereeAwardService::getDssTrailPayTaskId();
            } else {
                // DSSCRM-1841: 奖励推荐人168元，被推荐人50元：
                return RefereeAwardService::getDssYearPayTaskId();
            }
        } else {
            if ($packageType == DssPackageExtModel::PACKAGE_TYPE_TRIAL
                && in_array($trialType, [DssPackageExtModel::TRIAL_TYPE_49, DssPackageExtModel::TRIAL_TYPE_9])) {
                // DSSCRM-1841: 奖励推荐人1元：
                return RefereeAwardService::getDssTrailPayTaskId();
            } else {
                // 年卡：
                // 查询当前被推荐人是第几个：
                // 根据个数决定奖励
                $startPoint = DictConstants::get(DictConstants::REFERRAL_CONFIG, 'dsscrm_1841_start_time');
                if (!empty($refereeInfo['first_pay_normal_info']['create_time'])
                    && $refereeInfo['first_pay_normal_info']['create_time'] >= $startPoint) {
                    $startPoint = $refereeInfo['first_pay_normal_info']['create_time'];
                }
                $refereeData = StudentInviteModel::getRefereeBuyData(['referee_id' => $refereeInfo['id'], 'app_id' => $appId, 'referee_type' => StudentInviteModel::REFEREE_TYPE_STUDENT, 'create_time' => $startPoint]);
                if (empty($refereeData)) {
                    SimpleLogger::error("EMPTY REFEREE DATA", [$refereeInfo['id'], $startPoint]);
                    return 0;
                }
                // 被推荐人个数
                $refereeCount = 0;
                // 查询出来的被推荐人购买年卡数据一定包含当前购买人
                $includeFlag = false;
                foreach ($refereeData as $item) {
                    if ($item['create_time'] >= $startPoint) {
                        $refereeCount++;
                    }
                    // 筛选到当前被推荐人时停止计数
                    if ($item['buyer'] == $refereeInfo['student_id']) {
                        $includeFlag = true;
                        break;
                    }
                }
                if (empty($refereeCount) || !$includeFlag) {
                    SimpleLogger::error('RETURN TASK ID:0', [$refereeCount, $refereeData]);
                    return 0;
                }
                $noChangeNumber = DictConstants::get(DictConstants::REFERRAL_CONFIG, 'task_stop_change_number');
                if ($refereeCount > $noChangeNumber) {
                    $refereeCount = $noChangeNumber;
                }
                $config = DictConstants::get(DictConstants::REFERRAL_CONFIG, 'normal_task_config');
                $config = json_decode($config, true);
                if (empty($config)) {
                    SimpleLogger::error("EMPTY REFEREE TASK CONFIG", [$config]);
                }
                return $config[$refereeCount] ?? 0;
            }
        }
    }
}