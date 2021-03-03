<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/28
 * Time: 19:34
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Models\AgentAwardDetailModel;
use App\Models\AgentUserModel;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;
use App\Models\StudentInviteModel;


class AgentAwardService
{
    /**
     * 代理商发放奖励
     * @param $agentId
     * @param $studentInfo
     * @param $packageInfo
     * @param $actionType
     * @param int $parentBillId
     * @return bool
     */
    public static function agentReferralBillAward(int $agentId, $studentInfo, $actionType, $packageInfo = [], $parentBillId = 0)
    {
        //根据奖励动作类型执行不同奖励
        if (empty($studentInfo) || empty($actionType) || empty($agentId)) {
            return false;
        }
        //检测当前代理是否有效:包括父级
        $agentIsValid = AgentService::checkAgentStatusIsValid($agentId);
        if (empty($agentIsValid)) {
            SimpleLogger::info('agent status invalid', []);
            return false;
        }
        $time = time();
        switch ($actionType) {
            case AgentAwardDetailModel::AWARD_ACTION_TYPE_REGISTER:
                //注册
                self::registerAward($agentId, $studentInfo, $time);
                break;
            case AgentAwardDetailModel::AWARD_ACTION_TYPE_BUY_TRAIL_CLASS:
                //购买体验课
                self::buyTrailClassAward($agentId, $studentInfo, $packageInfo, $parentBillId, $time);
                break;
            case AgentAwardDetailModel::AWARD_ACTION_TYPE_BUY_FORMAL_CLASS:
                //购买正式课
                self::buyFormalClassAward($agentId, $studentInfo, $packageInfo, $parentBillId, $time);
                break;
            default:
                return false;
        }
        return true;
    }


    /**
     * 注册奖励
     * @param $agentId
     * @param $studentInfo
     * @param $time
     * @return bool
     */
    private static function registerAward($agentId, $studentInfo, $time)
    {
        $bindQuality = self::checkRegisterBindQuality($studentInfo['id']);
        if ($bindQuality === false) {
            return false;
        }
        $awardData = [
            'agent_id' => $agentId,
            'student_id' => $studentInfo['id'],
            'action_type' => AgentAwardDetailModel::AWARD_ACTION_TYPE_REGISTER,
            'ext' => "{}",
            'create_time' => $time,
        ];
        return self::recordRegisterAwardAndBindData($awardData);
    }

    /**
     * 购买体验课奖励
     * @param $agentId
     * @param $studentInfo
     * @param $packageInfo
     * @param $parentBillId
     * @param $time
     * @return bool
     */
    private static function buyTrailClassAward($agentId, $studentInfo, $packageInfo, $parentBillId, $time)
    {
        $bindQuality = self::checkTrailBindQuality($studentInfo['id']);
        if ($bindQuality === false) {
            return false;
        }
        //奖励
        $awardData = [
            'agent_id' => $agentId,
            'student_id' => $studentInfo['id'],
            'action_type' => AgentAwardDetailModel::AWARD_ACTION_TYPE_BUY_TRAIL_CLASS,
            'ext' => json_encode(['parent_bill_id' => $parentBillId, 'package_type' => $packageInfo['package_type'], 'package_id' => $packageInfo['package_id']]),
            'create_time' => $time,
        ];
        //绑定关系
        $bindData = [
            'bind_time' => $time,
            'deadline' => $time + DictConstants::get(DictConstants::AGENT_BIND, AgentUserModel::STAGE_TRIAL),
            'stage' => AgentUserModel::STAGE_TRIAL,
            'create_time' => $time,
        ];
        $res = self::recordTrailAwardAndBindData($awardData, $bindData, $parentBillId);
        if (empty($res)) {
            return false;
        }
        return true;
    }


    /**
     * 购买正式课奖励
     * @param $agentId
     * @param $studentInfo
     * @param $packageInfo
     * @param $parentBillId
     * @param $time
     * @return bool
     */
    private static function buyFormalClassAward($agentId, $studentInfo, $packageInfo, $parentBillId, $time)
    {
        $bindQuality = self::checkFormalBindQuality($studentInfo['id']);
        if ($bindQuality === false) {
            return false;
        }
        if ($packageInfo['app_id'] != Constants::SMART_APP_ID) {
            return false;
        }
        //奖励
        $bindData = [];
        $awardData = [
            'agent_id' => $agentId,
            'student_id' => $studentInfo['id'],
            'action_type' => AgentAwardDetailModel::AWARD_ACTION_TYPE_BUY_FORMAL_CLASS,
            'ext' => json_encode(['parent_bill_id' => $parentBillId, 'package_type' => $packageInfo['package_type'], 'package_id' => $packageInfo['package_id']]),
            'create_time' => $time,
        ];
        //获取学生是否存在有效的体验课绑定关系的代理数据
        $validBind = AgentUserModel::getValidBindData($studentInfo['id']);
        if (empty($validBind)) {
            $awardData['is_bind'] = AgentAwardDetailModel::IS_BIND_STATUS_NO;
        } else {
            //绑定关系
            $bindData = [
                'deadline' => 0,
                'stage' => AgentUserModel::STAGE_FORMAL,
                'update_time' => $time,
            ];
        }
        $res = self::recordFormalAwardAndBindData($awardData, $bindData);
        if (empty($res)) {
            return false;
        }
        return true;
    }

    /**
     * 注册奖励
     * @param $awardData
     * @return bool
     */
    private static function recordRegisterAwardAndBindData($awardData)
    {
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        //记录奖励详情数据
        $awardId = AgentAwardDetailModel::insertRecord($awardData);
        if (empty($awardId)) {
            SimpleLogger::error("agent award data record fail", [$awardData]);
            $db->rollBack();
            return false;
        }
        //记录关系绑定数据
        $bindData['agent_id'] = $awardData['agent_id'];
        $bindData['user_id'] = $awardData['student_id'];
        $bindData['create_time'] = $awardData['create_time'];
        $bindId = AgentUserModel::insertRecord($bindData);
        if (empty($bindId)) {
            SimpleLogger::error("agent user bind register data record fail", [$bindData]);
            $db->rollBack();
            return false;
        }
        $db->commit();
        return true;
    }

    /**
     * 购买体验课奖励
     * @param $awardData
     * @param $bindData
     * @param $parentBillId
     * @return bool
     */
    private static function recordTrailAwardAndBindData($awardData, $bindData, $parentBillId)
    {
        //正式发送奖励之前再次检查一次，避免奖励已发放
        $billIsValid = self::checkBillIsValid($parentBillId);
        if (empty($billIsValid)) {
            return false;
        }
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        //记录奖励详情数据
        $awardId = AgentAwardDetailModel::insertRecord($awardData);
        if (empty($awardId)) {
            SimpleLogger::error("agent trail award data record fail", [$awardData]);
            $db->rollBack();
            return false;
        }
        //记录关系绑定数据
        //检查用户是否存在与代理商的注册关系数据
        $registerBindData = AgentUserModel::getRecord(['agent_id' => $awardData['agent_id'], 'user_id' => $awardData['student_id'], 'stage' => AgentUserModel::STAGE_REGISTER], ['id']);
        if (empty($registerBindData)) {
            //插入购买体验课的绑定数据
            $bindData['agent_id'] = $awardData['agent_id'];
            $bindData['user_id'] = $awardData['student_id'];
            $bindId = AgentUserModel::insertRecord($bindData);
        } else {
            $updateData = [
                'bind_time' => $bindData['bind_time'],
                'deadline' => $bindData['bind_time'] + DictConstants::get(DictConstants::AGENT_BIND, AgentUserModel::STAGE_TRIAL),
                'stage' => AgentUserModel::STAGE_TRIAL,
                'update_time' => $bindData['bind_time'],
            ];
            //修改注册阶段为购买体验课阶段
            $bindId = AgentUserModel::updateRecord($registerBindData['id'], $updateData);
        }
        if (empty($bindId)) {
            SimpleLogger::error("agent user bind trail data record fail", [$bindData]);
            $db->rollBack();
            return false;
        }
        $db->commit();
        return true;
    }

    /**
     * 购买正式课奖励
     * @param $awardData
     * @param $bindData
     * @return bool
     */
    private static function recordFormalAwardAndBindData($awardData, $bindData)
    {
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        //记录奖励详情数据
        $awardId = AgentAwardDetailModel::insertRecord($awardData);
        if (empty($awardId)) {
            SimpleLogger::error("agent formal award data record fail", [$awardData]);
            $db->rollBack();
            return false;
        }
        //记录关系绑定数据
        if (!empty($bindData)) {
            $bindId = AgentUserModel::batchUpdateRecord($bindData, ['agent_id' => $awardData['agent_id'], 'user_id' => $awardData['student_id']]);
            if (empty($bindId)) {
                SimpleLogger::error("agent user bind formal data record fail", [$bindData]);
                $db->rollBack();
                return false;
            }
        }
        $db->commit();
        return true;
    }

    /**
     * 检查是否可以继续绑定代理商关系:注册
     * @param $studentId
     * @return bool
     */
    private static function checkRegisterBindQuality($studentId)
    {
        //检测转介绍关系
        $hasAgentReferral = self::checkReferralIsAgent($studentId);
        if (empty($hasAgentReferral)) {
            return false;
        }
        return true;
    }


    /**
     * 检查是否可以继续绑定代理商关系:购买体验课
     * @param $studentId
     * @return bool
     */
    private static function checkTrailBindQuality($studentId)
    {
        //检测转介绍关系
        $hasAgentReferral = self::checkReferralIsAgent($studentId);
        if (empty($hasAgentReferral)) {
            return false;
        }
        //学员先购买智能年卡，不可绑定
        $studentInfo = DssStudentModel::getById($studentId);
        $buyDss = DssGiftCodeModel::hadPurchasePackageByType($studentId, DssPackageExtModel::PACKAGE_TYPE_NORMAL);
        if ($studentInfo['has_review_course'] != DssStudentModel::REVIEW_COURSE_49
            && count($buyDss) >=1) {
            SimpleLogger::info('not an experience class to buy first', []);
            return false;
        }
        //已存在绑定关系不可绑定
        $agentBuyTrailBindInfo = AgentUserModel::getRecord(['user_id' => $studentId, 'stage[>=]' => AgentUserModel::STAGE_TRIAL], ['id']);
        if (!empty($agentBuyTrailBindInfo)) {
            SimpleLogger::info('student has buy trail', []);
            return false;
        }
        return true;
    }

    /**
     * 检查是否可以继续绑定代理商关系:购买正式课
     * @param $studentId
     * @return bool
     */
    private static function checkFormalBindQuality($studentId)
    {
        //检测转介绍关系
        $hasAgentReferral = self::checkReferralIsAgent($studentId);
        if (empty($hasAgentReferral)) {
            return false;
        }
        return true;
    }

    /**
     * 检测学生转介绍关系:无转介绍关系&代理转介绍返回true，否则返回false
     * @param $studentId
     * @return bool
     */
    public static function checkReferralIsAgent($studentId)
    {
        $referralInfo = StudentInviteModel::getRecord(['student_id' => $studentId, 'app_id' => Constants::SMART_APP_ID, 'referee_type[!]' => StudentInviteModel::REFEREE_TYPE_AGENT]);
        if ($referralInfo) {
            SimpleLogger::info('has bind student referee student relation', ['referral_info' => $referralInfo]);
            return false;
        }
        return true;
    }

    /**
     * 检测订单ID是否存在奖励发放记录
     * @param $parentBillId
     * @return bool
     */
    public static function checkBillIsValid($parentBillId)
    {
        $awardRecord = AgentAwardDetailModel::getDetailByParentBillId($parentBillId);
        if (!empty($awardRecord)) {
            SimpleLogger::info('bill have used', ['parent_bill_id' => $parentBillId, 'award_detail_id' => $awardRecord[0]['id']]);
            return false;
        }
        return true;
    }
}