<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-29
 * Time: 15:41
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\Valid;
use App\Models\StudentAccountLogModel;
use App\Models\StudentAccountModel;

class StudentAccountService
{
    /**
     * 添加账户金额数据
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

    /**
     * 扣减学生金额或虚拟币
     * @param $studentId
     * @param $amount
     * @param $operatorId
     * @param $remark
     * @return array|bool
     */
    public static function reduceSA($studentId, $amount, $operatorId, $remark)
    {
        $log = [];
        $cash = null;
        $vcash = null;
        $now = time();
        $sas = StudentAccountModel::getSADetailBySId($studentId);
        if (empty($sas)) {
            return Valid::addErrors([], 'student', 'student_account_not_enough');
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
                return Valid::addErrors([], 'student', 'update_student_account_error');
            }
        } else if ($cash['balance'] + $vcash['balance'] >= $amount) {
            $cashAmount = 0;
            if ($cash['balance'] > 0) {
                $cashAmount = $amount - $cash['balance'];
                $res = StudentAccountModel::updateSA(['update_time' => $now, 'balance' => 0, 'ver[+]' => 1], ['id' => $cash['id'], 'ver' => $cash['ver']]);
                if ($res > 0) {
                    $log[] = ['operator_id' => $operatorId, 'remark' => $remark, 'create_time' => $now, 's_a_id' => $cash['id'], 'balance' => $cashAmount, 'old_balance' => $cash['balance'], 'new_balance' => 0, 'type' => StudentAccountLogModel::TYPE_REDUCE];
                } else {
                    return Valid::addErrors([], 'student', 'update_student_account_error');
                }
            }
            $res = StudentAccountModel::updateSA(['update_time' => $now, 'balance[-]' => $amount - $cashAmount, 'ver[+]' => 1], ['id' => $vcash['id'], 'ver' => $vcash['ver']]);
            if ($res > 0) {
                $log[] = ['operator_id' => $operatorId, 'remark' => $remark, 'create_time' => $now, 's_a_id' => $vcash['id'], 'balance' => $amount - $cashAmount, 'old_balance' => $vcash['balance'], 'new_balance' => $vcash['balance'] - ($amount - $cashAmount), 'type' => StudentAccountLogModel::TYPE_REDUCE];
            } else {
                return Valid::addErrors([], 'student', 'update_student_account_error');
            }

        } else {
            return Valid::addErrors([], 'student', 'student_account_not_enough');
        }
        if (!empty($log)) {
            StudentAccountLogModel::batchInsert($log, false);
        }
        return true;
    }

    /**
     * 废弃学生金额账户
     * @param $studentId
     * @param $amount
     * @param $vamount
     * @param $operatorId
     * @param $remark
     * @param bool $force
     * @return bool|array
     */
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

    /**
     * 获取学生账户余额
     * @param $studentId
     * @return array
     */
    public static function getStudentAccount($studentId)
    {
        $accounts = StudentAccountModel::getRecords([
            'student_id' => $studentId,
            'type' => [StudentAccountModel::TYPE_CASH, StudentAccountModel::TYPE_VIRTUAL],
            'status' => StudentAccountModel::STATUS_NORMAL
        ]);
        foreach ($accounts as &$account) {
            $account['account_type'] = DictService::getKeyValue(Constants::DICT_TYPE_STUDENT_ACCOUNT_TYPE, $account['type']);
        }
        return $accounts;
    }

    /**
     * 获取学生账户操作记录
     * @param $studentId
     * @param $page
     * @param $count
     * @return array
     */
    public static function getLogs($studentId, $page, $count)
    {
        list($logs, $totalCount) = StudentAccountLogModel::getSALs($studentId, $page, $count);
        foreach ($logs as &$account) {
            $account['account_type'] = DictService::getKeyValue(Constants::DICT_TYPE_STUDENT_ACCOUNT_OPERATE_TYPE, $account['type']);
        }
        return [$logs, $totalCount];
    }
}