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
     * @param $billId
     * @param $operatorId
     * @param $remark
     * @return bool
     */
    public static function addSA($studentId, $data, $billId, $operatorId, $remark)
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
                            $log[] = ['operator_id' => $operatorId, 'remark' => $remark, 'create_time' => $now, 's_a_id' => $sa['id'], 'balance' => $balance, 'old_balance' => $sa['balance'], 'new_balance' => $balance + $sa['balance'], 'type' => StudentAccountLogModel::TYPE_ADD, 'bill_id' => $billId];
                        } else {
                            return false;
                        }
                    }
                }
                if ($insert == true) {
                    $saId = StudentAccountModel::insertRecord(['status' => StudentAccountModel::STATUS_NORMAL, 'create_time' => $now, 'student_id' => $studentId, 'balance' => $balance, 'type' => $type]);
                    if (!empty($saId)) {
                        $log[] = ['operator_id' => $operatorId, 'remark' => $remark, 'create_time' => $now, 's_a_id' => $saId, 'balance' => $balance, 'old_balance' => 0, 'new_balance' => $balance, 'type' => StudentAccountLogModel::TYPE_ADD, 'bill_id' => $billId];
                    }
                }
            }
        } else {
            foreach ($data as $type => $balance) {
                $saId = StudentAccountModel::insertRecord(['create_time' => $now, 'student_id' => $studentId, 'balance' => $balance, 'type' => $type]);
                if ($saId > 0) {
                    $log[] = ['operator_id' => $operatorId, 'remark' => $remark, 'create_time' => $now, 's_a_id' => $saId, 'balance' => $balance, 'old_balance' => 0, 'new_balance' => $balance, 'type' => StudentAccountLogModel::TYPE_ADD, 'bill_id' => $billId];
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
     * @param $scheduleId
     * @return array|bool
     */
    public static function reduceSA($studentId, $amount, $operatorId, $scheduleId = 0)
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

        $remark = '课次结束，扣费';
        $type = StudentAccountLogModel::TYPE_REDUCE;
        $deductError = Valid::addErrors([], 'student', 'update_student_account_error');
        if ($cash['balance'] >= $amount) {
            $res = StudentAccountModel::updateSA(['update_time' => $now, 'balance[-]' => $amount, 'ver[+]' => 1], ['id' => $cash['id'], 'ver' => $cash['ver']]);
            if ($res > 0) {
                $log[] = ['operator_id' => $operatorId, 'remark' => $remark, 'create_time' => $now, 's_a_id' => $cash['id'], 'balance' => $amount, 'old_balance' => $cash['balance'], 'new_balance' => $cash['balance'] - $amount, 'type' => $type, 'schedule_id' => $scheduleId];
            } else {
                return $deductError;
            }
        } else if ($cash['balance'] + $vcash['balance'] >= $amount) {
            if ($cash['balance'] > 0) {
                $res = StudentAccountModel::updateSA(['update_time' => $now, 'balance' => 0, 'ver[+]' => 1], ['id' => $cash['id'], 'ver' => $cash['ver']]);
                if ($res > 0) {
                    $log[] = ['operator_id' => $operatorId, 'remark' => $remark, 'create_time' => $now, 's_a_id' => $cash['id'], 'balance' => $cash['balance'], 'old_balance' => $cash['balance'], 'new_balance' => 0, 'type' => $type, 'schedule_id' => $scheduleId];
                } else {
                    return $deductError;
                }
            }

            $vCashAmount = $amount - $cash['balance'];
            $res = StudentAccountModel::updateSA(['update_time' => $now, 'balance[-]' => $vCashAmount, 'ver[+]' => 1], ['id' => $vcash['id'], 'ver' => $vcash['ver']]);
            if ($res > 0) {
                $log[] = ['operator_id' => $operatorId, 'remark' => $remark, 'create_time' => $now, 's_a_id' => $vcash['id'], 'balance' => $vCashAmount, 'old_balance' => $vcash['balance'], 'new_balance' => $vcash['balance'] - $vCashAmount, 'type' => $type, 'schedule_id' => $scheduleId];
            } else {
                return $deductError;
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
     * @param int $billId
     * @return bool|array
     */
    public static function abolishSA($studentId, $amount, $vamount, $operatorId, $remark, $force = true, $billId = 0) {

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

        if ($amount > 0) {
            if (empty($cash) || ($force == false && $cash['balance'] < $amount)) {
                return Valid::addErrors([], 'student_account', 'student_account_cash_is_not_enough');
            }
            $data = ['update_time' => $now, 'balance[-]' => $amount , 'ver[+]' => 1];
            if($force == true) {
                $data['status'] = StudentAccountModel::STATUS_CANCEL;
            }
            $res = StudentAccountModel::updateSA($data, ['id' => $cash['id'], 'ver' => $cash['ver']]);
            if ($res > 0) {
                $log[] = ['operator_id' => $operatorId, 'remark' => $remark, 'create_time' => $now, 's_a_id' => $cash['id'], 'balance' => $amount, 'old_balance' => $cash['balance'], 'new_balance' => $cash['balance'] - $amount, 'type' => StudentAccountLogModel::TYPE_DISCARD, 'bill_id' => $billId];
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
                $log[] = ['operator_id' => $operatorId, 'remark' => $remark, 'create_time' => $now, 's_a_id' => $vcash['id'], 'balance' => $vamount, 'old_balance' => $vcash['balance'], 'new_balance' => $vcash['balance'] - $vamount, 'type' => StudentAccountLogModel::TYPE_DISCARD, 'bill_id' => $billId];
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
     * @param $orgId
     * @return array
     */
    public static function getStudentAccounts($studentId, $orgId)
    {
        $accounts = StudentAccountModel::getStudentAccounts($studentId, $orgId);
        foreach ($accounts as &$account) {
            $account['account_type'] = DictService::getKeyValue(Constants::DICT_TYPE_STUDENT_ACCOUNT_TYPE, $account['type']);
        }
        return $accounts;
    }

    /**
     * 获取学生账户操作记录
     * @param $studentId
     * @param $orgId
     * @param $page
     * @param $count
     * @return array
     */
    public static function getLogs($studentId, $orgId, $page, $count)
    {
        list($logs, $totalCount) = StudentAccountLogModel::getSALs($studentId, $orgId, $page, $count);
        foreach ($logs as &$account) {
            $account['account_type'] = DictService::getKeyValue(Constants::DICT_TYPE_STUDENT_ACCOUNT_OPERATE_TYPE, $account['type']);
        }
        return [$logs, $totalCount];
    }

    /**
     * 检查账户余额是否充足
     * @param $students
     * @param $cts
     * @return array|bool
     */
    public static function checkBalance($students, $cts)
    {
        $studentIds = array_keys($students);
        global $orgId;

        // 学生购买的账户总金额
        $accounts = StudentAccountLogModel::getAddSALog($studentIds, $orgId);
        $balances = [];
        foreach ($accounts as $account) {
            if ($account['type'] == StudentAccountLogModel::TYPE_ADD) {
                $balances[$account['student_id']] += $account['balance'];
            } elseif ($account['type'] == StudentAccountLogModel::TYPE_DISCARD) {
                $balances[$account['student_id']] -= $account['balance'];
            }
        }

        $prices = [];
        $studentPrices = ClassTaskService::getTakeUpBalances($studentIds);
        foreach ($studentPrices as $studentPrice) {
            $prices[$studentPrice['user_id']] += $studentPrice['price'] * $studentPrice['period'];
        }

        foreach ($students as $key => $price) {
            if (empty($balances[$key])) {
                return Valid::addErrors([], 'students', 'student_account_is_not_enough');
            }

            $needPrices = 0;
            foreach ($price as $key1 => $value) {
                $needPrices = $value * $cts[$key1]['period'];
            }

            $prices[$key] = $prices[$key] ?? 0;
            if ($balances[$key] - $prices[$key] < $needPrices) {
                return Valid::addErrors([], 'students', 'student_account_is_not_enough');
            }
        }
        return true;
    }
}