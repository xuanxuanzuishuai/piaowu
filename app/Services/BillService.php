<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/29
 * Time: 上午10:23
 */

namespace App\Services;


use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Util;
use App\Models\ApprovalModel;
use App\Models\BillModel;
use App\Models\StudentAccountModel;

class BillService
{
    public static function selectByPage($orgId, $page, $count, $params)
    {
        list($page, $count) = Util::formatPageCount(['page' => $page, 'count' => $count]);

        list($records, $total) = BillModel::selectByPage($orgId, $page, $count, $params);
        foreach($records as &$r) {
            $r['pay_status']         = DictService::getKeyValue(Constants::DICT_TYPE_BILL_PAY_STATUS, $r['pay_status']);
            $r['pay_channel']        = DictService::getKeyValue(Constants::DICT_TYPE_BILL_PAY_CHANNEL, $r['pay_channel']);
            $r['source']             = DictService::getKeyValue(Constants::DICT_TYPE_BILL_SOURCE, $r['source']);
            $r['is_disabled']        = DictService::getKeyValue(Constants::DICT_TYPE_BILL_DISABLED, $r['is_disabled']);
            $r['is_enter_account']   = DictService::getKeyValue(Constants::DICT_TYPE_BILL_IS_ENTER_ACCOUNT, $r['is_enter_account']);
            $r['amount']             /= 100;
            $r['sprice']             /= 100;
            $r['add_status_zh']      = DictService::getKeyValue(Constants::DICT_TYPE_BILL_ADD_STATUS, $r['add_status']);
            $r['disabled_status_zh'] = DictService::getKeyValue(Constants::DICT_TYPE_BILL_DISABLED_STATUS, $r['disabled_status']);
        }
        return [$records, $total];
    }

    public static function getDetail($billId, $orgId = null)
    {
        $record = BillModel::getDetail($billId, $orgId);

        if(!empty($record)) {
            $record['pay_status']       = DictService::getKeyValue(Constants::DICT_TYPE_BILL_PAY_STATUS, $record['pay_status']);
            $record['pay_channel']      = DictService::getKeyValue(Constants::DICT_TYPE_BILL_PAY_CHANNEL, $record['pay_channel']);
            $record['source']           = DictService::getKeyValue(Constants::DICT_TYPE_BILL_SOURCE, $record['source']);
            $record['is_disabled']      = DictService::getKeyValue(Constants::DICT_TYPE_BILL_DISABLED, $record['is_disabled']);
            $record['is_enter_account'] = DictService::getKeyValue(Constants::DICT_TYPE_BILL_IS_ENTER_ACCOUNT, $record['is_enter_account']);
            $record['amount']           /= 100;
            $record['sprice']           /= 100;

            if(empty($record['credentials_url'])) {
               $record['credentials_url'] = [];
            } else {
                $list = explode(',', $record['credentials_url']);
                $map = [];
                foreach($list as $v) {
                    $map[$v] = AliOSS::signUrls($v);
                }
                $record['credentials_url'] = $map;
            }
        }

        return $record;
    }

    public static function onApproved($billId, $type)
    {
        $bill = BillModel::getRecord(['id' => $billId]);
        if(empty($bill)) {
            return 'bill_is_empty';
        }

        if($type == ApprovalModel::TYPE_BILL_ADD) {
            if($bill['add_status'] != BillModel::ADD_STATUS_APPROVING) {
                return 'bill_incorrect_status';
            }

            //更新订单状态
            $affectedRows = BillModel::updateRecord($billId, [
                'update_time' => time(),
                'add_status'  => BillModel::ADD_STATUS_APPROVED,
            ], false);
            if($affectedRows != 1) {
                return 'update_bill_add_status_fail';
            }

            //更新学生余额账户
            if($bill['pay_status'] == BillModel::PAY_STATUS_PAID &&
                $bill['is_enter_account'] == BillModel::IS_ENTER_ACCOUNT) {
                $success = StudentAccountService::addSA(
                    $bill['student_id'],
                    [StudentAccountModel::TYPE_CASH => $bill['amount']],
                    $bill['operator_id'],
                    $bill['remark']
                );
                if(!$success) {
                    return 'increase_student_account_fail';
                }
            }

            //更新学生首次付费状态
            if($bill['pay_status'] == BillModel::PAY_STATUS_PAID) {
                $rows = StudentService::updateUserPaidStatus($bill['student_id']);
                if(!is_null($rows) && empty($rows)) {
                    return 'update_first_pay_fail';
                }
            }
        } else if ($type == ApprovalModel::TYPE_BILL_DISABLE){
            if($bill['disabled_status'] != BillModel::DISABLED_STATUS_APPROVING) {
                return 'bill_incorrect_status';
            }

            $affectedRows = BillModel::updateRecord($billId, [
                'update_time'     => time(),
                'disabled_status' => BillModel::DISABLED_STATUS_APPROVED,
                'is_disabled'     => BillModel::IS_DISABLED,
            ], false);
            if($affectedRows != 1) {
                return 'update_bill_disabled_status_fail';
            }
            if($bill['pay_status'] == BillModel::PAY_STATUS_PAID &&
                $bill['is_enter_account'] == BillModel::IS_ENTER_ACCOUNT)
            {
                $success = StudentAccountService::abolishSA(
                    $bill['student_id'], $bill['amount'], 0, $bill['operator_id'], $bill['remark'], false
                );
                if(!$success) {
                    return 'update_student_account_fail';
                }
            }
        } else {
            return 'incorrect_type';
        }

        return null;
    }

    public static function onRejected($billId, $type)
    {
        $bill = BillModel::getRecord(['id' => $billId]);
        if(empty($bill)) {
            return 'bill_is_empty';
        }

        if($type == ApprovalModel::TYPE_BILL_ADD) {
            if($bill['add_status'] != BillModel::ADD_STATUS_APPROVING) {
                return 'bill_incorrect_status';
            }

            $affectedRows = BillModel::updateRecord($billId, [
                'update_time' => time(),
                'add_status'  => BillModel::ADD_STATUS_REJECTED,
            ], false);
            if($affectedRows != 1) {
                return 'update_bill_add_status_fail';
            }
        } else if ($type == ApprovalModel::TYPE_BILL_DISABLE) {
            if($bill['disabled_status'] != BillModel::DISABLED_STATUS_APPROVING) {
                return 'bill_incorrect_status';
            }

            $affectedRows = BillModel::updateRecord($billId, [
                'update_time'     => time(),
                'disabled_status' => BillModel::DISABLED_STATUS_REJECTED,
            ], false);
            if($affectedRows != 1) {
                return 'update_bill_disabled_status_fail';
            }
        } else {
            return 'incorrect_type';
        }

        return null;
    }

    public static function onRevoked($billId, $type)
    {
        $bill = BillModel::getRecord(['id' => $billId]);
        if(empty($bill)) {
            return 'bill_is_empty';
        }

        if($type == ApprovalModel::TYPE_BILL_ADD) {
            if($bill['add_status'] != BillModel::ADD_STATUS_APPROVING) {
                return 'bill_incorrect_status';
            }

            $affectedRows = BillModel::updateRecord($billId, [
                'update_time' => time(),
                'add_status'  => BillModel::ADD_STATUS_REVOKED,
            ], false);
            if($affectedRows != 1) {
                return 'update_bill_add_status_fail';
            }
        } else if ($type == ApprovalModel::TYPE_BILL_DISABLE) {
            if($bill['disabled_status'] != BillModel::DISABLED_STATUS_APPROVING) {
                return 'bill_incorrect_status';
            }

            $affectedRows = BillModel::updateRecord($billId, [
                'update_time' => time(),
                'disabled_status'  => BillModel::DISABLED_STATUS_REVOKED,
            ], false);
            if($affectedRows != 1) {
                return 'update_bill_disabled_status_fail';
            }
        } else {
            return 'incorrect_type';
        }

        return null;
    }
}