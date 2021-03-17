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
use App\Libs\EventListener\AgentAwardExtEvent;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Models\AgentAwardDetailModel;
use App\Models\AgentModel;
use App\Models\AgentUserModel;
use App\Models\StudentInviteModel;


class AgentAwardService
{
    /**
     * 代理商发放奖励
     * @param int $bindAgentId 关系绑定代理商ID
     * @param $studentInfo
     * @param $packageInfo
     * @param $actionType
     * @param int $parentBillId
     * @param int $billOwnAgentId 订单归属代理商ID
     * @return bool
     */
    public static function agentReferralBillAward($bindAgentId, $studentInfo, $actionType, $packageInfo = [], $parentBillId = 0, $billOwnAgentId = 0)
    {
        //判断基础数据是否合格
        if (empty($studentInfo) || empty($actionType)) {
            return false;
        }
        //检测是否需要绑定关系和订单归属
        if (($actionType == AgentAwardDetailModel::AWARD_ACTION_TYPE_BUY_TRAIL_CLASS || $actionType == AgentAwardDetailModel::AWARD_ACTION_TYPE_BUY_FORMAL_CLASS)
            && (empty($billOwnAgentId))) {
            SimpleLogger::info('no need award', []);
            return false;
        }
        $time = time();
        //根据奖励动作类型执行不同奖励
        switch ($actionType) {
            case AgentAwardDetailModel::AWARD_ACTION_TYPE_REGISTER:
                //注册
                self::registerAward($bindAgentId, $studentInfo, $time);
                break;
            case AgentAwardDetailModel::AWARD_ACTION_TYPE_BUY_TRAIL_CLASS:
                //购买体验课
                self::buyTrailClassAward($bindAgentId, $studentInfo, $packageInfo, $parentBillId, $time, $billOwnAgentId);
                break;
            case AgentAwardDetailModel::AWARD_ACTION_TYPE_BUY_FORMAL_CLASS:
                //购买正式课
                self::buyFormalClassAward($bindAgentId, $studentInfo, $packageInfo, $parentBillId, $time, $billOwnAgentId);
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
     * @param $bindAgentId
     * @param $studentInfo
     * @param $packageInfo
     * @param $parentBillId
     * @param $time
     * @param $billOwnAgentId
     * @return bool
     */
    private static function buyTrailClassAward($bindAgentId, $studentInfo, $packageInfo, $parentBillId, $time, $billOwnAgentId)
    {
        //奖励关系
        $agentInfo = AgentModel::getAgentParentData([$billOwnAgentId]);
        $awardData = [
            'agent_id' => $billOwnAgentId,
            'student_id' => $studentInfo['id'],
            'action_type' => AgentAwardDetailModel::AWARD_ACTION_TYPE_BUY_TRAIL_CLASS,
            'ext' => json_encode(['parent_bill_id' => $parentBillId, 'package_type' => $packageInfo['package_type'], 'package_id' => $packageInfo['package_id'], 'division_model' => $agentInfo[0]['division_model'], 'agent_type' => $agentInfo[0]['agent_type']]),
            'create_time' => $time,
            'is_bind' => AgentService::checkAwardIsBindStatus($studentInfo['id'], $billOwnAgentId, $bindAgentId),
        ];

        //绑定关系
        $bindData = [];
        if (!empty($bindAgentId)) {
            $bindData = [
                'agent_id' => $bindAgentId,
                'user_id' => $studentInfo['id'],
                'bind_time' => $time,
                'deadline' => $time + DictConstants::get(DictConstants::AGENT_BIND, AgentUserModel::STAGE_TRIAL),
                'stage' => AgentUserModel::STAGE_TRIAL,
                'create_time' => $time,
            ];
        }
        $res = self::recordTrailAwardAndBindData($awardData, $bindData, $parentBillId);
        if (empty($res)) {
            return false;
        }
        return true;
    }


    /**
     * 购买正式课奖励
     * @param $bindAgentId
     * @param $studentInfo
     * @param $packageInfo
     * @param $parentBillId
     * @param $time
     * @param $billOwnAgentId
     * @return bool
     */
    private static function buyFormalClassAward($bindAgentId, $studentInfo, $packageInfo, $parentBillId, $time, $billOwnAgentId)
    {
        //奖励
        $agentInfo = AgentModel::getAgentParentData([$billOwnAgentId]);
        $awardData = [
            'agent_id' => $billOwnAgentId,
            'student_id' => $studentInfo['id'],
            'action_type' => AgentAwardDetailModel::AWARD_ACTION_TYPE_BUY_FORMAL_CLASS,
            'ext' => json_encode(['parent_bill_id' => $parentBillId, 'package_type' => $packageInfo['package_type'], 'package_id' => $packageInfo['package_id'], 'division_model' => $agentInfo[0]['division_model'], 'agent_type' => $agentInfo[0]['agent_type']]),
            'create_time' => $time,
            'is_bind' => (int)AgentService::checkAwardIsBindStatus($studentInfo['id'], $billOwnAgentId, $bindAgentId),
        ];
        //绑定关系
        $bindData = [];
        if (!empty($bindAgentId)) {
            $bindData = [
                'deadline' => $time + DictConstants::get(DictConstants::AGENT_BIND, AgentUserModel::STAGE_FORMAL),
                'stage' => AgentUserModel::STAGE_FORMAL,
                'update_time' => $time,
                'create_time' => $time,
                'user_id' => $studentInfo['id'],
                'agent_id' => $bindAgentId,
                'bind_time' => $time,
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
        if (!empty($bindData)) {
            //检查用户是否存在与代理商的注册关系数据
            $bindId = true;
            $trailBindData = AgentUserModel::getRecord(['agent_id' => $bindData['agent_id'], 'user_id' => $bindData['user_id']], ['id', 'stage']);
            if (empty($trailBindData)) {
                //插入购买体验课的绑定数据
                $bindId = AgentUserModel::insertRecord($bindData);
            } elseif ($trailBindData['stage'] == AgentUserModel::STAGE_REGISTER) {
                $updateData = [
                    'bind_time' => $bindData['bind_time'],
                    'deadline' => $bindData['bind_time'] + DictConstants::get(DictConstants::AGENT_BIND, AgentUserModel::STAGE_TRIAL),
                    'stage' => AgentUserModel::STAGE_TRIAL,
                    'update_time' => $bindData['bind_time'],
                ];
                //修改注册阶段为购买体验课阶段
                $bindId = AgentUserModel::updateRecord($trailBindData['id'], $updateData);
            }
            if (empty($bindId)) {
                SimpleLogger::error("agent user bind trail data record fail", [$bindData]);
                $db->rollBack();
                return false;
            }
        }
        $db->commit();
        self::AgentAwardExtDataRecord($awardId);
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
            //检查用户是否存在与代理商的注册关系数据
            $bindId = true;
            $normalBindData = AgentUserModel::getRecord(['agent_id' => $bindData['agent_id'], 'user_id' => $bindData['user_id']], ['id', 'stage', 'deadline']);
            if (empty($normalBindData) ||
                (
                    ($normalBindData['stage'] == AgentUserModel::STAGE_FORMAL) ||
                    ($normalBindData['stage'] == AgentUserModel::STAGE_TRIAL) &&
                    ($normalBindData['deadline'] < time())
                )
            ) {
                //绑定关系不存在或者已解绑：重新插入绑定数据
                $insertBindData = $bindData;
                unset($insertBindData['update_time']);
                $bindId = AgentUserModel::insertRecord($insertBindData);
            } elseif (($normalBindData['stage'] == AgentUserModel::STAGE_REGISTER)) {
                //修改绑定关系数据
                $bindId = AgentUserModel::batchUpdateRecord($bindData, ['agent_id' => $bindData['agent_id'], 'user_id' => $bindData['user_id']]);
            }
            if (empty($bindId)) {
                SimpleLogger::error("agent user bind formal data record fail", [$bindData]);
                $db->rollBack();
                return false;
            }
        }
        $db->commit();
        self::AgentAwardExtDataRecord($awardId);
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
            SimpleLogger::info('bill have used', ['parent_bill_id' => $parentBillId, 'award_detail_id' => $awardRecord['id']]);
            return false;
        }
        return true;
    }

    /**
     * 记录代理商奖励订单的扩展信息
     * @param $agentAwardDetailId
     */
    private static function AgentAwardExtDataRecord($agentAwardDetailId)
    {
        $agentOpEventObj = new AgentAwardExtEvent(['agent_award_detail_id' => $agentAwardDetailId]);
        $agentOpEventObj::fire($agentOpEventObj);
    }
}