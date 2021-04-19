<?php

namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\AgentAwardDetailModel;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;
use App\Models\ParamMapModel;
use App\Models\StudentInviteModel;
use App\Models\StudentReferralStudentStatisticsModel;
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
    public static function refereeAwardDeal($appId, $eventType, $params)
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
        if ($appId == Constants::SMART_APP_ID) {
            //记录注册转介绍关系
            $inviteRes = StudentInviteService::studentInviteRecord($studentId, StudentReferralStudentStatisticsModel::STAGE_REGISTER, $appId, $qrTicket, $extParams);
            if (empty($inviteRes['record_res'])) {
                return false;
            }
            if ($inviteRes['invite_user_type'] == ParamMapModel::TYPE_AGENT) {
                //代理商转介绍发放奖励
                AgentAwardService::agentReferralBillAward($inviteRes['invite_user_id'], ['id' => $studentId], AgentAwardDetailModel::AWARD_ACTION_TYPE_REGISTER);
            } else {
                //学生转介绍发放奖励
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
            //代理奖励
            AgentService::agentAwardLogic($buyPreStudentInfo, $parentBillId, $packageInfo);
            // 更新用户微信标签
            WechatService::updateUserTagByUserId($buyPreStudentInfo['id'], true);
            //转介绍奖励
            self::dssBuyDeal($buyPreStudentInfo, $packageInfo, $parentBillId);
        }
        return true;
    }

    /**
     * dss付费事件处理
     * @param $buyPreStudentInfo
     * @param $packageInfo
     * @param $parentBillId
     * @throws RunTimeException
     */
    public static function dssBuyDeal($buyPreStudentInfo, $packageInfo, $parentBillId)
    {
        if (RefereeAwardService::dssShouldCompleteEventTask($buyPreStudentInfo, $packageInfo, $parentBillId)) {
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
        $refereeInfo['first_pay_normal_info'] = DssGiftCodeModel::getUserFirstPayInfo($refereeRelation['referee_id']);

        $refTaskId = self::getTaskIdByType($packageType, $trialType, $refereeInfo, $appId);
        if (!is_array($refTaskId) && !empty($refTaskId)) {
            $refTaskId = [$refTaskId];
        }

        $studentInfo = DssStudentModel::getById($studentId);
        if (!empty($refTaskId)) {
            $erp = new Erp();
            foreach ($refTaskId as $taskId) {
                $data = $erp->addEventTaskAward($studentInfo['uuid'], $taskId, self::EVENT_TASK_STATUS_COMPLETE);
                SimpleLogger::info("UserRefereeService::dssCompleteEventTask", [
                    'params' => [
                        $studentId,
                        $packageType,
                        $trialType,
                        $appId
                    ],
                    'reqParams' => [
                        $studentInfo['uuid'],
                        $taskId,
                        self::EVENT_TASK_STATUS_COMPLETE
                    ],
                    'response' => $data,
                ]);
            }
        }
    }

    /**
     * 根据购买类型获取对应任务ID
     * @param $packageType
     * @param $trialType
     * @param $refereeInfo
     * @param $appId
     * @return int|string|array
     */
    public static function getTaskIdByType($packageType, $trialType, $refereeInfo, $appId)
    {
        // 保留代码 后期新规则时使用
        // $newRuleStartPoint = DictConstants::get(DictConstants::REFERRAL_CONFIG, 'xyzop_178_start_point');
        // if (!empty($newRuleStartPoint) && time() >= $newRuleStartPoint) {
        //     return self::getTaskIdByTypeNew($packageType, $trialType, $refereeInfo, $appId);
        // }

        $taskIds = [];
        // 推荐人当前状态：非付费正式课：
        if ($refereeInfo['has_review_course'] != DssStudentModel::REVIEW_COURSE_1980) {
            if ($packageType == DssPackageExtModel::PACKAGE_TYPE_TRIAL
                && in_array($trialType, [DssPackageExtModel::TRIAL_TYPE_49, DssPackageExtModel::TRIAL_TYPE_9])) {
                // XYZOP-178-1.1: 奖励推荐人1元：
                $taskIds[] = RefereeAwardService::getDssTrailPayTaskId();
            } else {
                // XYZOP-178-1.2: 奖励推荐人100元，被推荐人50元：
                $taskIds[] = RefereeAwardService::getDssYearPayTaskId();
            }
        } else {
            // 推荐人状态：付费正式课
            // 被推荐人购买体验课：
            if ($packageType == DssPackageExtModel::PACKAGE_TYPE_TRIAL
                && in_array($trialType, [DssPackageExtModel::TRIAL_TYPE_49, DssPackageExtModel::TRIAL_TYPE_9])) {
                /** 推荐人购买体验卡 - 不在需要判断数量 现在发放的都是固定奖励 */
                // 确定时间起点：
                // $startPoint = DictConstants::get(DictConstants::REFERRAL_CONFIG, 'xyzop_178_start_point');
                // if (!empty($refereeInfo['first_pay_normal_info']['create_time'])
                //     && $refereeInfo['first_pay_normal_info']['create_time'] >= $startPoint) {
                //     $startPoint = $refereeInfo['first_pay_normal_info']['create_time'];
                // }
                // // 查询被推荐人数量：
                // $refereeCount = self::getRefereeCount($refereeInfo, $appId, $startPoint, DssStudentModel::REVIEW_COURSE_49);
                // $noChangeNumber = DictConstants::get(DictConstants::REFERRAL_CONFIG, 'trial_task_stop_change_number_xyzop_178');
                // if ($refereeCount > $noChangeNumber) {
                //     $refereeCount = $noChangeNumber;
                // }
                // // 根据配置返回taskId：
                // $config = DictConstants::get(DictConstants::REFERRAL_CONFIG, 'trial_task_config_xyzop_178');
                // $config = json_decode($config, true);
                // if (empty($config)) {
                //     SimpleLogger::error("EMPTY REFEREE TASK CONFIG", [$config]);
                // }
                // $taskId = $config[$refereeCount] ?? 0;
                // if (!empty($taskId)) {
                //     $taskIds[] = $taskId;
                // }
                $taskIds[] = RefereeAwardService::getDssTrailPayTaskId();
            } else {
                // 被推荐人购买年卡：
                // XYZOP-178-2.2.1
                $taskIds[] = RefereeAwardService::getDssYearPayTaskId(1);

                // 确定时间起点：
                $startPoint = strtotime(date("Y-m-01 00:00：00"));
                if (!empty($refereeInfo['first_pay_normal_info']['create_time'])
                    && $refereeInfo['first_pay_normal_info']['create_time'] >= $startPoint) {
                    $startPoint = $refereeInfo['first_pay_normal_info']['create_time'];
                }
                $endPoint = strtotime(date("Y-m-01 00:00:00", strtotime("+1 month")));

                // 查询被推荐人数量：
                $refereeCount = self::getRefereeCount($refereeInfo, $appId, $startPoint, $endPoint, DssStudentModel::REVIEW_COURSE_1980);
                $noChangeNumber = DictConstants::get(DictConstants::REFERRAL_CONFIG, 'task_stop_change_number');
                if ($refereeCount > $noChangeNumber) {
                    $refereeCount = $noChangeNumber;
                }
                // 根据配置返回taskId：
                $config = DictConstants::get(DictConstants::REFERRAL_CONFIG, 'normal_task_config');
                $config = json_decode($config, true);
                if (empty($config)) {
                    SimpleLogger::error("EMPTY REFEREE TASK CONFIG", [$config]);
                }
                $taskId = $config[$refereeCount] ?? 0;
                if (!empty($taskId)) {
                    $taskIds[] = $taskId;
                }

                // XYZOP-178-2.2.2
                if (time() <= $refereeInfo['first_pay_normal_info']['create_time'] + Util::TIMESTAMP_ONEDAY * 15) {
                    $extraTaskId = DictConstants::get(DictConstants::REFERRAL_CONFIG, 'extra_task_id_normal_xyzop_178');
                    $startPoint = DictConstants::get(DictConstants::REFERRAL_CONFIG, 'xyzop_178_start_point');
                    $extraCount = StudentInviteModel::getRefereeExtraTask(
                        [
                            'referee_id' => $refereeInfo['id'],
                            'create_time' => $startPoint,
                            'end_time' => $endPoint
                        ],
                        $extraTaskId
                    );
                    if (empty($extraCount)) {
                        $taskIds[] = $extraTaskId;
                    }
                }
            }
        }
        return $taskIds;
    }

    /**
     * 查询被推荐人数量
     * @param $refereeInfo
     * @param $appId
     * @param $startPoint
     * @param int $type
     * @return int
     */
    public static function getRefereeCount($refereeInfo, $appId, $startPoint, $endPoint, $type = DssStudentModel::REVIEW_COURSE_1980)
    {
        $refereeData = StudentInviteModel::getRefereeBuyData(
            [
                'referee_id' => $refereeInfo['id'],
                'app_id' => $appId,
                'referee_type' => StudentInviteModel::REFEREE_TYPE_STUDENT,
                'create_time' => $startPoint
            ],
            $type
        );
        if (empty($refereeData)) {
            SimpleLogger::error("EMPTY REFEREE DATA", [$refereeInfo, $startPoint, $type]);
            return 0;
        }
        // 被推荐人个数
        $refereeCount = 0;
        // 查询出来的被推荐人购买数据一定包含当前购买人
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
            SimpleLogger::error('RETURN TASK ID:0', [$refereeCount, $refereeInfo, $refereeData]);
            return 0;
        }
        return $refereeCount;
    }
}