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

class ApprovalService
{
    public static function needApprove()
    {
        $config = ApprovalConfigService::getValidConfig();
        return !empty($config);
    }

    public static function submit($billId, $operator)
    {
        $config = ApprovalConfigService::getValidConfig();

        if (empty($config)) {
            return ['invalid_approval_config'];
        }

        if ($config['status'] == Constants::STATUS_FALSE) {
            return ['invalid_status'];
        }

        $roles = explode(',', $config['roles']);

        $id = ApprovalModel::insertRecord([
            'config_id' => $config['id'],
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

        $result = BillService::onRevoked($approval['bill_id']);
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
        if ($employee['role'] != $approval['current_role']) {
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

            $finishErrorCode = self::finish(false);

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

                $finishErrorCode = self::finish($approval['bill_id'], true);
            }
        }

        if (!empty($finishErrorCode)) {
            SimpleLogger::debug(__FILE__ . ':' . __LINE__ . ' approve result', ['error' => $finishErrorCode]);
            return $finishErrorCode;
        }

        AppLogModel::insertRecord([
            'approval_id' => $id,
            'op_type' => $opType,
            'operator' => $operator,
            'create_time' => time(),
            'remark' => $remark
        ], false);

        return null;
    }

    private static function finish($billId, $isApproved)
    {
        if ($isApproved) {
             return BillService::onApproved($billId);
        } else {
             return BillService::onRejected($billId);
        }
    }
}
