<?php
/**
 * Created by PhpStorm.
 * Student: yuxuan
 * Date: 2018/11/9
 * Time: 6:14 PM
 */

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\DingDing;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\EmployeeModel;
use App\Models\ApplyAwardModel;
use App\Models\StudentModel;
use App\Models\WeChatAwardCashDealModel;

class ApplyAwardService
{
    /**
     * @param $employeeId
     * @param $studentId
     * @param $eventTaskId
     * @param $reissueReason
     * @param $imageKeyArr
     * @throws RunTimeException
     * 发起审核
     */
    public static function applyAward($employeeId, $studentId, $eventTaskId, $reissueReason, $imageKeyArr)
    {
        $time = time();
        $studentInfo = StudentModel::getById($studentId);
        //校验数据
        if (empty($studentInfo)) {
            throw new RunTimeException(['student_not_exist']);
        }
        $employeeInfo = EmployeeModel::getRecord(['id' => $employeeId]);
        $info = (new DingDing())->getMobileByUuid(['uuid' => $employeeInfo['uuid']]);
        if (empty($info['mobile'])) {
            throw new RunTimeException(['not_bind_ding_ding']);
        }
        $awardInfo = ErpReferralService::getExpectTaskIdRelateAward($eventTaskId);
        $id = ApplyAwardModel::insertRecord(['supply_employee_uuid' => $employeeInfo['uuid'],
            'student_id' => $studentId,
            'expect_event_task_id' => $eventTaskId,
            'amount' => intval(reset($awardInfo)['award']) * 100,
            'reissue_reason' => $reissueReason,
            'image_key' => !empty($imageKeyArr[0]) ? json_encode($imageKeyArr) : NULL,
            'create_time' => $time,
            'update_time' => $time,
            'status' => ApplyAwardModel::START_SUPPLY]);
        if (empty($id)) {
            throw new RunTimeException(['insert table fail']);
        }
        list($temNo, $url) = DictConstants::get(DictConstants::DING_DING_CONFIG, ['tem_no', 'flow_url']);
        //发起钉钉审批
        $data = (new DingDing())->sponsorApply(
            [
                'process_code' => $temNo,
                'originator_uuid' => $employeeInfo['uuid'],
                'form_component_values' => [
                    [
                        'name' => '-----',
                        'value' => '[点此链接查看详细审核内容](' . $url . $id . ")"
                    ]
                ]
            ]
        );
        if (empty($data['workflow_instance_id'])) {
            throw new RunTimeException(['ding_ding_request_error']);
        }
        ApplyAwardModel::updateRecord($id, ['workflow_id' => $data['workflow_instance_id']]);
    }

    /**
     * @param $params
     * @param $page
     * @param $count
     * @return array
     * 申请列表
     */
    public static function getApplyList($params, $page, $count)
    {
        list($info, $count) = ApplyAwardModel::getList($params, $page, $count);
        return [self::formatList($info), $count];
    }

    /**
     * @param $info
     * @return array
     * 格式化输出
     */
    private static function formatList($info)
    {
        $returnInfo = [];
        array_map(function ($item) use(&$returnInfo){
            $returnInfo[] = self::formatOne($item);
        }, $info);
        return $returnInfo;
    }

    private static function formatOne($item)
    {
        $allStatus = DictConstants::getSet(DictConstants::DING_DING_STATUS);
        $item['status_zh'] = $allStatus[$item['status']];
        $item['mobile'] = Util::hideUserMobile($item['mobile']);
        $item['task_zh'] = ErpReferralService::REISSUE_CASH_AWARD[$item['expect_event_task_id']];
        $item['create_time'] = date('Y-m-d H:i:s', $item['create_time']);
        $item['image_url'] = $item['image_key'] ? array_map(function ($item) {
            return AliOSS::replaceCdnDomainForDss($item);
        },json_decode($item['image_key'], true)) : [];
        $item['amount'] = $item['amount'] / 100;
        $item['amount_zh'] = $item['amount'] . '元';
        $item['give_info'] = NULL;
        if (!empty($item['cash_award_id'])) {
            $awardInfo = WeChatAwardCashDealModel::getRecord(['user_event_task_award_id' => $item['cash_award_id']]);
            $item['give_info'] = [
                'id' => $awardInfo['user_event_task_award_id'],
                'award_amount' => $awardInfo['award_amount'] / 100 . '元',
                'status_zh' => ErpReferralService::AWARD_STATUS[$awardInfo['status']]
            ];
        }
        return $item;
    }

    /**
     * @param $id
     * @return array
     * @throws RunTimeException
     * 申请详情
     */
    public static function getApplyDetail($id)
    {
        $returnInfo = [];
        $info = self::formatOne(ApplyAwardModel::getList(['id' => $id])[0][0]);
        $returnInfo['base_info'] = $info;
        $workflowDetailInfo = (new DingDing())->getApplyDetail(['workflow_instance_id' => $info['workflow_id']]);
        $dingWorkflowArr = [];
        array_map(function ($item) use(&$dingWorkflowArr){
                $item['operation'] = $item['operation_result'] == DingDing::NONE ? DingDing::getOperationTypeZh($item['operation_type']) : DingDing::getOperationResultZh($item['operation_result']);
                $dingWorkflowArr[] = $item;
        }, $workflowDetailInfo);
        $returnInfo['ding_info'] = $dingWorkflowArr;
        return $returnInfo;
    }

    /**
     * @param $params
     * @throws RunTimeException
     * 处理钉钉回调
     */
    public static function dealDingCallBack($params)
    {
        //根据流程处理状态 仅处理 拒绝/通过/撤销
        $status = NULL;
        $cashAwardId = NULL;
        //审批流程的正常结束
        if ($params['event_type'] == DingDing::BPMS_INSTANCE_CHANGE && $params['type'] == DingDing::BPMS_FINISH) {
            if ($params['result'] == DingDing::BPMS_AGREE) {
                //同意
                $status = ApplyAwardModel::SUPPLY_PASS;
            } elseif ($params['result'] == DingDing::BPMS_REFUSE) {
                //拒绝
                $status = ApplyAwardModel::SUPPLY_REFUSE;
            }
        }

        //审批流程终止（发起人撤销审批单）
        if ($params['event_type'] == DingDing::BPMS_INSTANCE_CHANGE && $params['type'] == DingDing::BPMS_BACK) {
            $status = ApplyAwardModel::SUPPLY_BACK;
        }

        //对应的审批信息
        $applyAwardInfo = ApplyAwardModel::getRecord(['workflow_id' => $params['process_instance_id']]);
        if (empty($applyAwardInfo)) {
            SimpleLogger::info('not apply award relate', ['workflow_id' => $params['process_instance_id']]);
            return;
        }

        if (empty($status)) {
            return;
        }
        //为通过的时候走红包发送
        if ($status == ApplyAwardModel::SUPPLY_PASS) {
            //真正对应的task_id
            $taskId = ErpReferralService::expectTaskRelateRealTask($applyAwardInfo['expect_event_task_id'])[0];
            //创建红包
            $studentInfo = StudentModel::getById($applyAwardInfo['student_id']);
            $employeeInfo = EmployeeModel::getRecord(['uuid' => $applyAwardInfo['supply_employee_uuid']]);
            $awardInfo = (new Erp())->updateTask($studentInfo['uuid'], $taskId, ErpReferralService::EVENT_TASK_STATUS_COMPLETE);
            if (empty($awardInfo['data']['user_award_ids'])) {
                return;
            }
            ErpReferralService::updateAward(
                $awardInfo['data']['user_award_ids'][0],
                ErpReferralService::AWARD_STATUS_GIVEN,
                $employeeInfo['id'],
                '',
                WeChatAwardCashDealModel::REISSUE_PIC_WORD
            );
            $cashAwardId = $awardInfo['data']['user_award_ids'][0];
        }
        ApplyAwardModel::updateRecord($applyAwardInfo['id'], ['cash_award_id' => $cashAwardId, 'status' => $status, 'update_time' => time()]);
    }
}