<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/31
 * Time: 11:09 AM
 */

namespace App\Services;


use App\Libs\DictConstants;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\EmployeeModel;
use App\Models\GiftCodeModel;
use App\Models\StudentModel;

class ErpService
{
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
                return $ret;
            } else {
                $student = StudentModel::getById($ret['student_id']);
            }
        }

        if (empty($student)) {
            return Valid::addErrors([], 'student_id', 'user_register_fail');
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

        return $giftCodes;
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
        if (($code['generate_channel'] != GiftCodeModel::BUYER_TYPE_ERP_EXCHANGE) ||
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

        if ($code['code_status'] == GiftCodeModel::CODE_STATUS_NOT_REDEEMED) {
            // 未激活的激活码直接禁用
            GiftCodeService::abandonCode($code['id']);

        } elseif ($code['code_status'] == GiftCodeModel::CODE_STATUS_HAS_REDEEMED) {

            // 已激活的扣除响应时间
            StudentService::reduceSubDuration($code['apply_user'], $code['valid_num'], $code['valid_units']);
        }

        return null;
    }
}