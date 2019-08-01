<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/31
 * Time: 11:09 AM
 */

namespace App\Services;


use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\EmployeeModel;
use App\Models\GiftCodeModel;
use App\Models\StudentModel;

class ErpService
{
    /**
     * @param $studentData
     * @param $exchangeType
     * @param $erpBillId
     * @param $erpBillAmount
     * @param int $giftCodeNum
     * @param int $giftCodeUnit
     * @return array [string errorCode, array giftCodes]
     */
    public static function exchangeGiftCode($studentData,
                                            $exchangeType,
                                            $erpBillId,
                                            $erpBillAmount,
                                            $giftCodeNum = 1,
                                            $giftCodeUnit = GiftCodeModel::CODE_TIME_YEAR)
    {
        $student = StudentService::getByUuid($studentData['uuid']);

        if (empty($student)) {
            if ($exchangeType == GiftCodeModel::BUYER_TYPE_ERP_EXCHANGE) {
                $studentData['channel_id'] = StudentModel::CHANNEL_ERP_EXCHANGE;
            } elseif ($exchangeType == GiftCodeModel::BUYER_TYPE_ERP_ORDER) {
                $studentData['channel_id'] = StudentModel::CHANNEL_ERP_ORDER;
            }
            if (empty($studentData['name'])) {
                $studentData['name'] = Util::defaultStudentName($studentData['mobile']);
            }
            $ret = StudentService::studentRegister($studentData, EmployeeModel::SYSTEM_EMPLOYEE_ID);

            if ($ret['code'] == Valid::CODE_PARAMS_ERROR) {
                $errorCode = array_values($ret['errors'])[0]['err_no'];
                return [$errorCode, null];
            } else {
                $student = StudentModel::getById($ret['student_id']);
            }
        }

        if (empty($student)) {
            return ['user_register_fail', null];
        }

        $orgId = DictConstants::get(DictConstants::SPECIAL_ORG_ID, 'panda');
        StudentService::bindOrg($orgId, $student['id']);

        $giftCodes = GiftCodeService::batchCreateCode(
            1,
            $giftCodeNum,
            $giftCodeUnit,
            $exchangeType,
            $student['id'],
            GiftCodeModel::CREATE_BY_SYSTEM,
            NULL,
            EmployeeModel::SYSTEM_EMPLOYEE_ID,
            time(),
            $erpBillId,
            $erpBillAmount);

        if (empty($giftCodes)) {
            return ['create_gift_code_fail', null];
        }

        return [null, $giftCodes];
    }

    public static function exchangeSMSData($giftCode)
    {
        $sign = CommonServiceForApp::SIGN_STUDENT_APP;
        $content = "激活码：{$giftCode}";
        return [$sign, $content];
    }

    /**
     * @param $billId
     * @param $uuid
     * @return null|string $errorCode
     */
    public static function abandonGiftCode($billId, $uuid)
    {
        $code = GiftCodeModel::getByBillId($billId);
        if (($code['generate_channel'] != GiftCodeModel::BUYER_TYPE_ERP_EXCHANGE) &&
            ($code['generate_channel'] != GiftCodeModel::BUYER_TYPE_ERP_ORDER)) {
            return 'code_generate_channel_invalid';
        }

        $student = StudentService::getByUuid($uuid);
        if ($code['buyer'] != $student['id']) {
            return 'buyer_invalid';
        }

        if ($code['code_status'] == GiftCodeModel::CODE_STATUS_INVALID) {
            return 'code_status_invalid';
        }

        if ($code['code_status'] == GiftCodeModel::CODE_STATUS_HAS_REDEEMED) {
            // 已激活的扣除响应时间
            $cnt = StudentService::reduceSubDuration($code['apply_user'], $code['valid_num'], $code['valid_units']);
            if (empty($cnt)) { return 'data_error'; }
        }

        $cnt = GiftCodeService::abandonCode($code['id'], true);
        if (empty($cnt)) { return 'data_error'; }

        return null;
    }

    /**
     * 将一个用户名下的服务时长和激活码信息转移到另一个用户
     * @param $srcUuid
     * @param $dstUuid
     * @return null|string
     */
    public static function giftCodeTransfer($srcUuid, $dstUuid)
    {
        $srcStudent = StudentService::getByUuid($srcUuid);
        if (empty($srcStudent)) {
            return 'buyer_invalid';
        }

        $dstStudent = StudentService::getByUuid($dstUuid);
        if (empty($dstStudent)) {
            $ret = StudentService::studentRegisterByUuid($dstUuid,
                StudentModel::CHANNEL_ERP_ORDER,
                EmployeeModel::SYSTEM_EMPLOYEE_ID);

            if ($ret['code'] == Valid::CODE_PARAMS_ERROR) {
                $errorCode = array_values($ret['errors'])[0]['err_no'];
                return $errorCode;
            } else {
                $dstStudent = StudentModel::getById($ret['student_id']);
            }
        }

        if (empty($dstStudent)) {
            return 'user_register_fail';
        }

        $now = time();

        $dstStudentUpdate = [
            'sub_start_date' => $srcStudent['sub_start_date'],
            'sub_end_date' => $srcStudent['sub_end_date'],
            'trial_start_date' => $srcStudent['trial_start_date'],
            'trial_end_date' => $srcStudent['trial_end_date'],
            'update_time'  => $now,
        ];
        $cnt = StudentModel::updateRecord($dstStudent['id'], $dstStudentUpdate, false);
        if (empty($cnt)) { return 'data_error'; }

        $srcStudentUpdate = [
            'sub_start_date' => 0,
            'sub_end_date' => 0,
            'update_time'  => $now,
        ];
        $cnt = StudentModel::updateRecord($srcStudent['id'], $srcStudentUpdate, false);
        if (empty($cnt)) { return 'data_error'; }

        GiftCodeModel::batchUpdateRecord(['apply_user' => $dstStudent['id']], ['apply_user' => $srcStudent['id']], false);
        GiftCodeModel::batchUpdateRecord(['buyer' => $dstStudent['id']], ['buyer' => $srcStudent['id']], false);

        return null;
    }
}