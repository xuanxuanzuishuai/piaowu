<?php

namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\AgentAwardDetailModel;
use App\Models\DictModel;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpUserEventTaskAwardGoldLeafModel;
use App\Models\ParamMapModel;
use App\Models\StudentInviteModel;
use App\Models\StudentReferralStudentDetailModel;
use App\Models\StudentReferralStudentStatisticsModel;
use App\Services\Queue\PushMessageTopic;
use App\Services\Queue\QueueService;


class UserRefereeService
{
    const EVENT_TYPE_REGISTER = 'event_type_register';
    const EVENT_TYPE_BUY = 'event_type_buy';
    /** 任务状态 */
    const EVENT_TASK_STATUS_COMPLETE = 2;
    const REFERRAL_AWARD_RULE_VERSION = 2;   //奖励规则版本
    const REFERRAL_AWARD_RULE_VERSION_CACHE_KEY = 'referral_award_rule_version';   //奖励规则版本缓存key
    const REFERRAL_AWARD_RULE_VERSION_START = '2021-07-08';   //奖励规则开始时间
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
            self::dssCompleteEventTask($buyPreStudentInfo['id'], $packageInfo['package_type'], $packageInfo['trial_type'], $packageInfo['app_id'], $parentBillId);
        }
    }


    /**
     * 完成转介绍任务
     * @param $studentId
     * @param $packageType
     * @param $trialType
     * @param $appId
     * @param $parentBillId
     * @throws RunTimeException
     */
    public static function dssCompleteEventTask($studentId, $packageType, $trialType, $appId, $parentBillId = '')
    {
        //当前用户的学生推荐人
        $refereeRelation = StudentReferralStudentStatisticsModel::getRecord(['student_id' => $studentId]);
        if (empty($refereeRelation)) {
            SimpleLogger::info('not referee relate', ['student_id' => $studentId, 'app_id' => Constants::SMART_APP_ID]);
            return;
        }
        $refereeInfo = DssStudentModel::getRecord(['id' => $refereeRelation['referee_id']]);
        $refereeInfo['student_id'] = $studentId;
        $refereeInfo['first_pay_normal_info'] = DssGiftCodeModel::getUserFirstPayInfo($refereeRelation['referee_id']);
        SimpleLogger::info('UserRefereeService::dssCompleteEventTask', ['ref' => $refereeInfo, 'stu_id' => $studentId]);
        
        $refTaskId = self::getTaskIdByType($packageType, $trialType, $refereeInfo, $appId, $parentBillId);
        SimpleLogger::info("UserRefereeService::dssCompleteEventTask", ['taskid' => $refTaskId]);
        
        if (!empty($refTaskId)) {
            $sendStatus = ErpUserEventTaskAwardGoldLeafModel::STATUS_WAITING;
            $studentInviteStage =  StudentReferralStudentStatisticsModel::STAGE_FORMAL;
            // 购买体验卡直接发放积分
            if ($packageType == DssPackageExtModel::PACKAGE_TYPE_TRIAL) {
                $studentInviteStage = StudentReferralStudentStatisticsModel::STAGE_TRIAL;
            }
            // 获取用户购买年卡时的转介绍信息
            $studentYearCardStageInfo = StudentReferralStudentDetailModel::getRecord(['student_id' => $studentId, 'stage' => $studentInviteStage], ['id']);
            $studentInfo = DssStudentModel::getById($studentId);
            $erp = new Erp();
            foreach ($refTaskId as $taskId) {
                $taskResult = $erp->addEventTaskAward($studentInfo['uuid'], $taskId, $sendStatus, 0, $refereeInfo['uuid'], [
                    'bill_id' => $parentBillId,
                    'package_type' => $packageType,
                    'invite_detail_id' => $studentYearCardStageInfo['id'],
                ]);
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
                    'response' => $taskResult,
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
    public static function getTaskIdByType($packageType, $trialType, $refereeInfo, $appId, $parentBillId)
    {
        $taskIds = [];
        $time = time();
        
        $redis = RedisDB::getConn();
        //新版本规则第一次上线时,删除旧规则缓存
        if ($time >= strtotime(self::REFERRAL_AWARD_RULE_VERSION_START)) {
            $version = $redis->get(self::REFERRAL_AWARD_RULE_VERSION_CACHE_KEY);
            if (empty($version) || $version != self::REFERRAL_AWARD_RULE_VERSION) {
                //删除旧规则缓存
                DictModel::delCache(DictConstants::NODE_RELATE_TASK, 'dict_list_');
                //设置version缓存为当前版本
                $redis->set(self::REFERRAL_AWARD_RULE_VERSION_CACHE_KEY, self::REFERRAL_AWARD_RULE_VERSION);
            }
        }
        
        // 推荐人当前状态：非付费正式课：
        if ($refereeInfo['has_review_course'] != DssStudentModel::REVIEW_COURSE_1980) {
            // XYZOP-555 : 奖励推荐人500金叶子：(推荐人是体验课用户, 被推荐人购买体验课)
            if ($refereeInfo['has_review_course'] == DssStudentModel::REVIEW_COURSE_49
                &&
                $packageType == DssPackageExtModel::PACKAGE_TYPE_TRIAL
            ) {
                $taskIds[] = RefereeAwardService::getDssTrailPayTaskId();
            }
        } elseif (strtotime($refereeInfo['sub_end_date'])+86400>=$time && $refereeInfo['sub_status']==DssStudentModel::SUB_STATUS_ON) {
            // 推荐人状态：付费正式课
            // 被推荐人购买体验课：
            if ($packageType == DssPackageExtModel::PACKAGE_TYPE_TRIAL) {
                /** 推荐人购买体验卡 - 不在需要判断数量 现在发放的都是固定奖励 */
                $taskIds[] = RefereeAwardService::getDssTrailPayTaskId(1);
            } else {
                // 被推荐人购买年卡：
                // XYZOP-555
                $totalInfo = DssGiftCodeModel::getRecord(['parent_bill_id' => $parentBillId], ['id', 'valid_num', 'valid_units']);
                if (empty($totalInfo)) {
                    return $taskIds;
                }
                $level = 0;
                switch ($totalInfo['valid_units']) {
                    case DssGiftCodeModel::CODE_TIME_DAY:
                        if ($totalInfo['valid_num']>=361 && $totalInfo['valid_num']<=371) {
                            $level = 1;
                        }
                        if ($totalInfo['valid_num']>=544 && $totalInfo['valid_num']<=554) {
                            $level = 2;
                        }
                        if ($totalInfo['valid_num']>=727) {
                            $level = 3;
                        }
                        break;
                    case DssGiftCodeModel::CODE_TIME_MONTH:
                        if ($totalInfo['valid_num']>=12 && $totalInfo['valid_num']<=17) {
                            $level = 1;
                        }
                        if ($totalInfo['valid_num']>=18 && $totalInfo['valid_num']<=23) {
                            $level = 2;
                        }
                        if ($totalInfo['valid_num']>=24) {
                            $level = 3;
                        }
                        break;
                    case DssGiftCodeModel::CODE_TIME_YEAR:
                        if ($totalInfo['valid_num']==1) {
                            $level = 1;
                        }
                        if ($totalInfo['valid_num']>1 && $totalInfo['valid_num']<2) {
                            $level = 2;
                        }
                        if ($totalInfo['valid_num']>=2) {
                            $level = 3;
                        }
                        break;
                }
                if ($level) {
                    $levelMap = [
                        1 => 0,
                        2 => 1,
                        3 => 2,
                    ];
                    $taskIds[] = RefereeAwardService::getDssYearPayTaskId($levelMap[$level]);
                }
                $firstBuyTime = $refereeInfo['first_pay_normal_info']['create_time'] ?? 0;
                if ($level && $time - $firstBuyTime <= 7*24*3600) {   //第一次购买年卡7天内,给推荐人和被推荐人额外奖励
                    $taskIds[] = RefereeAwardService::getDssYearPayTaskId(3);
                }
            }
        }
        return $taskIds;
    }

    /**
     * 查询被推荐人是推荐人指定时间内成功推荐的第几个人
     * 时间是 自然月，  类型年卡
     * @param $refereeInfo
     * @param $startPoint
     * @param int $type
     * @return int
     */
    public static function getRefereeCount($refereeInfo, $startPoint, $type = DssStudentModel::REVIEW_COURSE_1980, $noChangeNumber = 1000)
    {
        // 查询当前推荐关系是否存在
        $where = [
            'referee_id' => $refereeInfo['id'],
            'last_stage' => $type,
            'student_id' => $refereeInfo['student_id'],
        ];
        $refereeStudentData = StudentReferralStudentStatisticsModel::getRecord($where, ['id']);
        if (empty($refereeStudentData)) {
            return 0;
        }

        $refereeCount = 0;
        // 确定当前被推荐人是第几个成功购买年卡的人
        $list = StudentReferralStudentStatisticsModel::getStudentList($refereeInfo['id'], $type, $startPoint, [0,$noChangeNumber]);
        foreach ($list as $item) {
            $refereeCount +=1;
            if ($item['student_id'] == $refereeInfo['student_id']) {
                break;
            }
        }
        return  $refereeCount == 0 ? $noChangeNumber : $refereeCount;
    }
}