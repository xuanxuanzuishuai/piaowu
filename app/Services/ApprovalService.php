<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/6/13
 * Time: 10:45 AM
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Models\AppLogModel;
use App\Models\ApprovalConfigModel;
use App\Models\ApprovalLogModel;
use App\Models\ApprovalModel;
use App\Models\EmployeeModel;
use App\Models\RoleModel;

class ApprovalService
{
    public static function needApprove($type)
    {
        $config = ApprovalConfigService::getValidConfig($type);
        return !empty($config);
    }

    public static function submit($billId, $type, $operator)
    {
        $config = ApprovalConfigService::getValidConfig($type);

        if (empty($config)) {
            return ['invalid_approval_config'];
        }

        if ($config['status'] == Constants::STATUS_FALSE) {
            return ['invalid_status'];
        }

        $roles = explode(',', $config['roles']);

        $id = ApprovalModel::insertRecord([
            'config_id' => $config['id'],
            'type' => $type,
            'bill_id' => $billId,
            'create_time' => time(),
            'operator' => $operator,
            'status' => ApprovalModel::STATUS_WAITING,
            'current_level' => 0,
            'current_role' => $roles[0]
        ], true);

        if (empty($id)) {
            return ['insert_failure'];
        }

        return [null, $id];
    }

    public static function revoke($id, $operator)
    {
        $approval = ApprovalModel::getById($id);
        if (empty($approval)) {
            return 'record_not_found';
        }

        if ($approval['operator'] != $operator) {
            return 'invalid_operator';
        }

        if ($approval['status'] != ApprovalModel::STATUS_WAITING) {
            return 'closed_approval';
        }

        $result = BillService::onRevoked($approval['bill_id'], $approval['type']);
        if(!is_null($result)) {
            return $result;
        }

        $count = ApprovalModel::updateRecord($id, [
            'status' => ApprovalModel::STATUS_REVOKED
        ], false);

        if ($count <= 0) {
            return 'update_failure';
        }

        return null;
    }


    public static function approve($id, $opType, $operator, $remark)
    {
        $approval = ApprovalModel::getById($id);
        if (empty($approval)) {
            return 'record_not_found';
        }

        if ($approval['status'] != ApprovalModel::STATUS_WAITING) {
            return 'closed_approval';
        }

        $employee = EmployeeModel::getById($operator);
        if ($employee['role_id'] != $approval['current_role']) {
            return 'invalid_role';
        }

        $config = ApprovalConfigModel::getById($approval['config_id']);
        if (empty($config) || $config['status'] == Constants::STATUS_FALSE) {
            return 'invalid_approval_config';
        }

        if ($opType == ApprovalLogModel::OP_REJECT) {
            // 审批驳回
            $count = ApprovalModel::updateRecord($id, [
                'status' => ApprovalModel::STATUS_REJECTED
            ], false);

            if ($count <= 0) {
                return 'update_failure';
            }

            $finishErrorCode = self::finish($approval['bill_id'], $approval['type'], false);

        } elseif ($opType == ApprovalLogModel::OP_APPROVE) {

            $nextLevel = $approval['current_level'] + 1;

            if ($nextLevel < $config['levels']) {
                // 进入下一级审批
                $roles = explode(',', $config['roles']);
                $nextRole = $roles[$nextLevel];
                $count = ApprovalModel::updateRecord($id, [
                    'current_level' => $nextLevel,
                    'current_role' => $nextRole,
                ], false);

                if ($count <= 0) {
                    return 'update_failure';
                }

            } else {
                // 审批通过
                $count = ApprovalModel::updateRecord($id, [
                    'status' => ApprovalModel::STATUS_APPROVED
                ], false);

                if ($count <= 0) {
                    return 'update_failure';
                }

                $finishErrorCode = self::finish($approval['bill_id'], $approval['type'], true);
            }
        }

        if (!empty($finishErrorCode)) {
            SimpleLogger::debug(__FILE__ . ':' . __LINE__ . ' approve result', ['error' => $finishErrorCode]);
            return $finishErrorCode;
        }

        ApprovalLogModel::insertRecord([
            'approval_id' => $id,
            'level' => $approval['current_level'],
            'op_type' => $opType,
            'operator' => $operator,
            'create_time' => time(),
            'remark' => $remark
        ], false);

        return null;
    }

    private static function finish($billId, $type, $isApproved)
    {
        if ($isApproved) {
             return BillService::onApproved($billId, $type);
        } else {
             return BillService::onRejected($billId, $type);
        }
    }

    public static function getInfo($billId, $employeeId)
    {
        $approvals = ApprovalModel::getRecords([
            'bill_id' => $billId,
            'ORDER' => 'id'
        ], '*', true);

        if (empty($approvals)) {
            return [];
        }

        $employee = EmployeeModel::getById($employeeId);

        $configs = [];
        $roles = [];

        $approvalIdx = [];
        foreach ($approvals as $i => $approval) {
            $configId = $approval['config_id'];

            if (empty($configs[$configId])) {
                $config = ApprovalConfigModel::getById($approval['config_id']);
                if ($config['status'] == Constants::STATUS_FALSE) {
                    continue;
                }
                $configs[$configId] = $config;
            }

            $roleIds = explode(',', $configs[$configId]['roles']);

            $levelData = [];
            foreach ($roleIds as $level => $roleId) {

                if (empty($roles[$roleId])) {
                    $role = RoleModel::getById($roleId);
                    $roles[$roleId] = $role;
                }

                $levelData[$level] = [
                    'role_id' => $roleId,
                    'role_name' => $roles[$roleId]['name'],
                ];

                if ($roleId == $employee['role_id'] && $level >= $approval['current_level']) {
                    $levelData[$level]['can_approve'] = true;
                }
            }

            $approvals[$i]['level_data'] = $levelData;
            $operator = EmployeeModel::getById($approval['operator']);
            $approval['operator_name'] = $operator['name'];

            $approvalIdx[$approval['id']] = $i;
        }

        $logs = ApprovalLogModel::getRecords(['approval_id' => array_keys($approvalIdx)], '*', false);
        foreach ($logs as $log) {
            if (!empty($approvals[$approvalIdx[$log['approval_id']]]['level_data'][$log['level']])) {
                $approvals[$approvalIdx[$log['approval_id']]]['level_data'][$log['level']]['op_type'] = $log['op_type'];
                $approvals[$approvalIdx[$log['approval_id']]]['level_data'][$log['level']]['op_time'] = $log['create_time'];;
                $approvals[$approvalIdx[$log['approval_id']]]['level_data'][$log['level']]['remark'] = $log['remark'];
                $approvals[$approvalIdx[$log['approval_id']]]['level_data'][$log['level']]['operator'] = $log['operator'];
                $operator = EmployeeModel::getById($log['operator']);
                $approvals[$approvalIdx[$log['approval_id']]]['level_data'][$log['level']]['operator_name'] = $operator['name'];
            }
        }

        return $approvals;
    }

    public static function selectByPage($page, $count, $params)
    {
        list($records, $total) = ApprovalModel::selectByPage($page, $count, $params);
        foreach($records as &$r) {
            $r['status_zh'] = DictService::getKeyValue(Constants::DICT_TYPE_APPROVAL_STATUS, $r['status']);
            $r['type_zh']   = DictService::getKeyValue(Constants::DICT_TYPE_APPROVAL_TYPE, $r['type']);
        }
        return [$records, $total];
    }
}
