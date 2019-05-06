<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-29
 * Time: 15:41
 */

namespace App\Services;


use App\Libs\Valid;
use App\Models\StudentAccountLogModel;
use App\Models\StudentAccountModel;

class StudentAccountService
{
    /**
     * @param $studentId
     * @param $data
     * @param $operatorId
     * @param $remark
     * @return bool
     */
    public static function addSA($studentId, $data, $operatorId, $remark)
    {
        $log = [];
        $now = time();
        $sas = StudentAccountModel::getSADetailBySId($studentId);
        if (!empty($sas)) {
            foreach ($data as $type => $balance) {
                $insert = true;
                foreach ($sas as $sa) {
                    if (!empty($data[$sa['type']])) {
                        $balance = $data[$sa['type']];
                        $res = StudentAccountModel::updateSA(['update_time' => $now, 'balance[+]' => $balance, 'ver[+]' => 1], ['id' => $sa['id'], 'ver' => $sa['ver']]);
                        if ($res > 0) {
                            $insert = false;
                            $log[] = ['operator_id' => $operatorId, 'remark' => $remark, 'create_time' => $now, 's_a_id' => $sa['id'], 'balance' => $balance, 'old_balance' => $sa['balance'], 'new_balance' => $balance + $sa['balance'], 'type' => StudentAccountLogModel::TYPE_ADD];
                        } else {
                            return false;
                        }
                    }
                }
                if ($insert == true) {
                    $saId = StudentAccountModel::insertRecord(['status' => StudentAccountModel::STATUS_NORMAL, 'create_time' => $now, 'student_id' => $studentId, 'balance' => $balance, 'type' => $type]);
                    if (!empty($saId)) {
                        $log[] = ['operator_id' => $operatorId, 'remark' => $remark, 'create_time' => $now, 's_a_id' => $saId, 'balance' => $balance, 'old_balance' => 0, 'new_balance' => $balance, 'type' => StudentAccountLogModel::TYPE_ADD];
                    }
                }
            }
        } else {
            foreach ($data as $type => $balance) {
                $saId = StudentAccountModel::insertRecord(['create_time' => $now, 'student_id' => $studentId, 'balance' => $balance, 'type' => $type]);
                if ($saId > 0) {
                    $log[] = ['operator_id' => $operatorId, 'remark' => $remark, 'create_time' => $now, 's_a_id' => $saId, 'balance' => $balance, 'old_balance' => 0, 'new_balance' => $balance, 'type' => StudentAccountLogModel::TYPE_ADD];
                } else {
                    return false;
                }
            }
        }
        if (!empty($log)) {
            $res = StudentAccountLogModel::batchInsert($log, false);
        }
        return true;
    }

    public static function reduceSA($studentId, $amount, $operatorId, $remark)
    {
        $log = [];
        $cash = null;
        $vcash = null;
        $now = time();
        $sas = StudentAccountModel::getSADetailBySId($studentId);
        if (empty($sas)) {
            return false;
        }
        //先消耗现金 后消耗虚拟币
        foreach ($sas as $sa) {
            if ($sa['type'] == StudentAccountModel::TYPE_CASH) {
                $cash = $sa;
            } elseif ($sa['type'] == StudentAccountModel::TYPE_VIRTUAL) {
                $vcash = $sa;
            }
        }
        if ($cash['balance'] >= $amount) {
            $res = StudentAccountModel::updateSA(['update_time' => $now, 'balance[-]' => $amount, 'ver[+]' => 1], ['id' => $cash['id'], 'ver' => $cash['ver']]);
            if ($res > 0) {
                $log[] = ['operator_id' => $operatorId, 'remark' => $remark, 'create_time' => $now, 's_a_id' => $cash['id'], 'balance' => $amount, 'old_balance' => $cash['balance'], 'new_balance' => $cash['balance'] - $amount, 'type' => StudentAccountLogModel::TYPE_REDUCE];
            } else {
                return false;
            }
        } else if ($cash['balance'] + $vcash['balance'] >= $amount) {
            $cashAmount = 0;
            if ($cash['balance'] > 0) {
                $cashAmount = $amount - $cash['balance'];
                $res = StudentAccountModel::updateSA(['update_time' => $now, 'balance' => 0, 'ver[+]' => 1], ['id' => $cash['id'], 'ver' => $cash['ver']]);
                if ($res > 0) {
                    $log[] = ['operator_id' => $operatorId, 'remark' => $remark, 'create_time' => $now, 's_a_id' => $cash['id'], 'balance' => $cashAmount, 'old_balance' => $cash['balance'], 'new_balance' => 0, 'type' => StudentAccountLogModel::TYPE_REDUCE];
                } else {
                    return false;
                }
            }
            $res = StudentAccountModel::updateSA(['update_time' => $now, 'balance[-]' => $amount - $cashAmount, 'ver[+]' => 1], ['id' => $vcash['id'], 'ver' => $vcash['ver']]);
            if ($res > 0) {
                $log[] = ['operator_id' => $operatorId, 'remark' => $remark, 'create_time' => $now, 's_a_id' => $vcash['id'], 'balance' => $amount - $cashAmount, 'old_balance' => $vcash['balance'], 'new_balance' => $vcash['balance'] - ($amount - $cashAmount), 'type' => StudentAccountLogModel::TYPE_REDUCE];
            } else {
                return false;
            }

        } else {
            return false;
        }
        if (!empty($log)) {
            $res = StudentAccountLogModel::batchInsert($log, false);
        }
        return true;
    }

    public static function abolishSA($studentId, $amount, $vamount,$operatorId, $remark,$force = true) {

        $now = time();
        $sas = StudentAccountModel::getSADetailBySId($studentId);
        if (empty($sas)) {
            return false;
        }

        $cash = $vcash = 0;
        //先消耗现金 后消耗虚拟币
        foreach ($sas as $sa) {
            if ($sa['type'] == StudentAccountModel::TYPE_CASH) {
                $cash = $sa;
            } elseif ($sa['type'] == StudentAccountModel::TYPE_VIRTUAL) {
                $vcash = $sa;
            }
        }
        if($amount > 0 ) {
            if (empty($cash) || ($force == false && $cash['balance'] < $amount)) {
                return Valid::addErrors([], 'student_account', 'student_account_cash_is_not_enough');
            }
            $data = ['update_time' => $now, 'balance[-]' => $amount , 'ver[+]' => 1];
            if($force == true) {
                $data['status'] = StudentAccountModel::STATUS_CANCEL;
            }
            $res = StudentAccountModel::updateSA($data, ['id' => $cash['id'], 'ver' => $cash['ver']]);
            if ($res > 0) {
                $log[] = ['operator_id' => $operatorId, 'remark' => $remark, 'create_time' => $now, 's_a_id' => $cash['id'], 'balance' => $amount, 'old_balance' => $cash['balance'], 'new_balance' => $cash['balance'] - $amount, 'type' => StudentAccountLogModel::TYPE_REDUCE];
            } else {
                return false;
            }
        }

        if($vamount > 0 ) {
            if (empty($vcash) || ($force == false && $vcash['balance'] < $vamount)) {
                return Valid::addErrors([], 'student_account', 'student_account_vcash_is_not_enough');
            }
            $data = ['update_time' => $now, 'balance[-]' => $vamount , 'ver[+]' => 1];
            if($force == true) {
                $data['status'] = StudentAccountModel::STATUS_CANCEL;
            }
            $res = StudentAccountModel::updateSA($data, ['id' => $vcash['id'], 'ver' => $vcash['ver']]);
            if ($res > 0) {
                $log[] = ['operator_id' => $operatorId, 'remark' => $remark, 'create_time' => $now, 's_a_id' => $vcash['id'], 'balance' => $vamount, 'old_balance' => $vcash['balance'], 'new_balance' => $vcash['balance'] - $vamount, 'type' => StudentAccountLogModel::TYPE_REDUCE];
            } else {
                return false;
            }
        }
        if (!empty($log)) {
            $res = StudentAccountLogModel::batchInsert($log, false);
        }
        return true;
    }
}