<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/28
 * Time: 19:34
 */

namespace App\Services;


use App\Libs\SimpleLogger;
use App\Models\AgentAwardDetailModel;
use App\Models\AgentAwardBillExtModel;
use App\Models\AgentBillMapModel;
use App\Models\AgentModel;
use App\Models\AgentUserModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\StudentInviteModel;


class AgentAwardExtService
{
    /**
     * 记录代理商奖励订单的扩展信息
     * @param $agentAwardDetailId
     * @return bool
     */
    public static function addAgentAwardExtData($agentAwardDetailId)
    {
        $time = time();
        //获取奖励数据
        $agentAwardData = AgentAwardDetailModel::getRecord(['id' => $agentAwardDetailId], ['student_id', 'agent_id', 'ext']);
        if (empty($agentAwardData)) {
            return false;
        }
        $agentAwardExtData = json_decode($agentAwardData['ext'], true);
        //获取学生转介绍数据
        $studentReferralData = StudentInviteModel::getRecord(['student_id' => (int)$agentAwardData['student_id'], 'referee_type' => StudentInviteModel::REFEREE_TYPE_STUDENT], ['referee_id']);
        $studentReferralId = !empty($studentReferralData['referee_id']) ? $studentReferralData['referee_id'] : 0;

        //获取订单成单人数据
        $billMapData = AgentBillMapModel::get($agentAwardExtData['parent_bill_id'], $agentAwardData['student_id']);
        $signerAgentId = !empty($billMapData['agent_id']) ? $billMapData['agent_id'] : 0;

        //订单归属代理商与学生的绑定关系状态
//        $agentBindData = AgentUserModel::getAgentStudentLastBindData($agentAwardData['agent_id'], $agentAwardData['student_id']);

        //学生当前有效绑定关系的代理商数据
        $validAgentBindData = AgentUserModel::getValidBindData($agentAwardData['student_id']);
        $validAgentId = !empty($validAgentBindData) ? $validAgentBindData['agent_id'] : 0;
        //代理奖励订单扩展信息
        $agentAwardBillExtData = [
            'student_id' => $agentAwardData['student_id'],
            'parent_bill_id' => $agentAwardExtData['parent_bill_id'],
            'student_referral_id' => $studentReferralId,
            'own_agent_id' => $agentAwardData['agent_id'],
            'signer_agent_id' => $signerAgentId,
            'is_hit_order' => AgentAwardBillExtModel::IS_HIT_ORDER_NO,
            'is_first_order' => AgentAwardBillExtModel::IS_FIRST_ORDER_YES,
            'is_agent_channel_buy' => empty($billMapData) ? AgentAwardBillExtModel::IS_AGENT_CHANNEL_BUY_NO : AgentAwardBillExtModel::IS_AGENT_CHANNEL_BUY_YES,
            'create_time' => $time,
            'own_agent_status' => AgentModel::STATUS_OK,
            'signer_agent_status' => AgentModel::STATUS_OK,//如果没有成单人，默认为正常
        ];
        //检测当前订单是否为学生和代理商绑定关系后，首次购买年卡
//        if (($agentAwardExtData['package_type'] == DssPackageExtModel::PACKAGE_TYPE_NORMAL) && (!empty($agentBindData))) {
        if (($agentAwardExtData['package_type'] == DssPackageExtModel::PACKAGE_TYPE_NORMAL)) {
            $normalOrder = AgentAwardDetailModel::getAgentStudentBillCountByPackageType($agentAwardData['agent_id'], $agentAwardData['student_id'], DssPackageExtModel::PACKAGE_TYPE_NORMAL);
            if ((int)$normalOrder[0]['data_count'] > 1) {
                $agentAwardBillExtData['is_first_order'] = AgentAwardBillExtModel::IS_FIRST_ORDER_NO;
            }
        }
        //检测是否撞单:1.学生有推荐人 2.学生有绑定期中的代理 3.成单代理与绑定期的代理不是同一个,任何两种及两种以上的关系则为撞单
        $isHitOrderCondition = [];
        //学生的推荐人
        if ($studentReferralData) {
            $isHitOrderCondition['student_referral_data'] = 'yes';
        }
        //学生绑定期中的代理
        if ($validAgentId) {
            $isHitOrderCondition['bill_map_data'] = 'yes';
        }
        //成单人代理和学生绑定期中的代理不是同一个人
        if (!empty($validAgentId) && ($signerAgentId != $validAgentId)) {
            $isHitOrderCondition['agent_bind_data'] = 'yes';
        }
        if (!empty($isHitOrderCondition)) {
            $hitOrderRes = array_count_values($isHitOrderCondition);
            if ($hitOrderRes['yes'] >= 2) {
                $agentAwardBillExtData['is_hit_order'] = AgentAwardBillExtModel::IS_HIT_ORDER_YES;
            }
        }
        //查询订单归属代理商和成单代理商的状态
        $agentValidStatus = AgentService::checkAgentStatusIsValid([$signerAgentId, $agentAwardData['agent_id']]);
        if (!empty($signerAgentId)) {
            $agentAwardBillExtData['signer_agent_status'] = $agentValidStatus[$signerAgentId];
        }
        if (!empty($validAgentId)) {
            $agentAwardBillExtData['own_agent_status'] = $agentValidStatus[$agentAwardData['agent_id']];
        }
        $res = AgentAwardBillExtModel::insertRecord($agentAwardBillExtData);
        if (empty($res)) {
            SimpleLogger::error("agent award detail ext data record error", $agentAwardBillExtData);
            return false;
        }
        return true;
    }
}