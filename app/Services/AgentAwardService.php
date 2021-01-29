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
    public static function agentReferralBillAward($agentId, $studentInfo, $actionType, $packageInfo = [], $parentBillId = 0)
    {
        //根据奖励动作类型执行不同奖励
        if (empty($studentInfo) || empty($actionType) || empty($agentId)) {
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
        return self::recordAwardAndBindData($awardData);
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
        //绑定关系
        $packageInfo['parent_bill_id'] = $parentBillId;
        $awardData = [
            'agent_id' => $agentId,
            'student_id' => $studentInfo['id'],
            'action_type' => AgentAwardDetailModel::AWARD_ACTION_TYPE_BUY_TRAIL_CLASS,
            'ext' => json_encode($packageInfo),
            'create_time' => $time,
        ];
        $bindData = [
            'bind_time' => $time,
            'deadline' => $time + DictConstants::get(DictConstants::AGENT_BIND, AgentUserModel::STAGE_TRIAL),
            'stage' => AgentUserModel::STAGE_TRIAL,
            'create_time' => $time,
        ];
        return self::recordAwardAndBindData($awardData, $bindData);
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
        //此版本没有正式课暂时不处理----2021.1.29
        return true;
    }

    /**
     * 记录数据
     * @param $awardData
     * @param $bindData
     * @return bool
     */
    private static function recordAwardAndBindData($awardData, $bindData = [])
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
        $bindId = AgentUserModel::insertRecord($bindData);
        if (empty($bindId)) {
            SimpleLogger::error("agent user bind data record fail", [$bindData]);
            $db->rollBack();
            return false;
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
        //是否已经绑定学生转介绍学生绑定关系
        $bindInfo = StudentInviteModel::getRecord(['student_id' => $studentId, 'app_id' => Constants::SMART_APP_ID]);
        if (!empty($bindInfo)) {
            SimpleLogger::info('has bind student referee student relation', ['bind_info' => $bindInfo]);
            return false;
        }
        //已存在代理商关系不可注册绑定
        $agentBindInfo = AgentUserModel::getRecord(['user_id' => $studentId], ['id']);
        if (!empty($agentBindInfo)) {
            SimpleLogger::info('student has register', []);
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
        //是否已经绑定学生转介绍学生绑定关系
        $bindInfo = StudentInviteModel::getRecord(['student_id' => $studentId, 'app_id' => Constants::SMART_APP_ID]);
        if (!empty($bindInfo)) {
            SimpleLogger::info('has bind student referee student relation', ['bind_info' => $bindInfo]);
            return false;
        }
        //学员先购买智能年卡，不可绑定
        $studentInfo = DssStudentModel::getById($studentId);
        if ($studentInfo['has_review_course'] != DssStudentModel::REVIEW_COURSE_49) {
            SimpleLogger::info('not an experience class to buy first', []);
            return false;
        }
        //已存在体验卡绑定关系不可注册绑定
        $agentBuyTrailBindInfo = AgentUserModel::getRecord(['user_id' => $studentId, 'stage' => AgentUserModel::STAGE_TRIAL], ['id']);
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
        //是否已经绑定学生转介绍学生绑定关系
        $bindInfo = StudentInviteModel::getRecord(['student_id' => $studentId, 'app_id' => Constants::SMART_APP_ID]);
        if (!empty($bindInfo)) {
            SimpleLogger::info('has bind student referee student relation', ['bind_info' => $bindInfo]);
            return false;
        }
        //此版本没有正式课暂时不处理----2021.1.29
        return true;
    }
}