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
use App\Models\BillModel;
use App\Models\StudentAccountModel;

class BillService
{
    public static function selectByPage($orgId, $page, $count, $params)
    {
        list($page, $count) = Util::formatPageCount(['page' => $page, 'count' => $count]);

        list($records, $total) = BillModel::selectByPage($orgId, $page, $count, $params);
        foreach($records as &$r) {
            $r['pay_status']       = DictService::getKeyValue(Constants::DICT_TYPE_BILL_PAY_STATUS, $r['pay_status']);
            $r['pay_channel']      = DictService::getKeyValue(Constants::DICT_TYPE_BILL_PAY_CHANNEL, $r['pay_channel']);
            $r['source']           = DictService::getKeyValue(Constants::DICT_TYPE_BILL_SOURCE, $r['source']);
            $r['is_disabled']      = DictService::getKeyValue(Constants::DICT_TYPE_BILL_DISABLED, $r['is_disabled']);
            $r['is_enter_account'] = DictService::getKeyValue(Constants::DICT_TYPE_BILL_IS_ENTER_ACCOUNT, $r['is_enter_account']);
            $r['amount']           /= 100;
            $r['sprice']           /= 100;
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

    public static function onApproved($billId)
    {
        $bill = BillModel::getRecord(['id' => $billId]);
        if(empty($bill)) {
            return 'bill_is_empty';
        }
        if($bill['status'] != BillModel::STATUS_APPROVING) {
            return 'bill_incorrect_status';
        }
        $affectedRows = BillModel::updateRecord($billId, [
            'update_time' => time(),
            'status'      => BillModel::STATUS_APPROVED,
        ], false);
        if($affectedRows != 1) {
            return 'update_bill_status_fail';
        }

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

        $rows = StudentService::updateUserPaidStatus($bill['student_id']);
        if(!is_null($rows) && empty($rows)) {
            return 'update_first_pay_fail';
        }

        return null;
    }

    public static function onRejected($billId)
    {
        $bill = BillModel::getRecord(['id' => $billId]);
        if(empty($bill)) {
            return 'bill_is_empty';
        }
        if($bill['status'] != BillModel::STATUS_APPROVING) {
            return 'bill_incorrect_status';
        }

        $affectedRows = BillModel::updateRecord($billId, [
            'update_time' => time(),
            'status'      => BillModel::STATUS_REJECTED,
            'is_disabled' => BillModel::IS_DISABLED,
        ], false);
        if($affectedRows != 1) {
            return 'update_bill_status_fail';
        }

        return null;
    }

    public static function onRevoked($billId)
    {
        $bill = BillModel::getRecord(['id' => $billId]);
        if(empty($bill)) {
            return 'bill_is_empty';
        }
        if($bill['status'] != BillModel::STATUS_APPROVING) {
            return 'bill_incorrect_status';
        }

        $affectedRows = BillModel::updateRecord($billId, [
            'update_time' => time(),
            'status'      => BillModel::STATUS_REVOKED,
            'is_disabled' => BillModel::IS_DISABLED,
        ], false);
        if($affectedRows != 1) {
            return 'update_bill_status_fail';
        }

        return null;
    }
}