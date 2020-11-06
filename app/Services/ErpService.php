<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/31
 * Time: 11:09 AM
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\EmployeeModel;
use App\Models\GiftCodeDetailedModel;
use App\Models\GiftCodeModel;
use App\Models\StudentLeaveLogModel;
use App\Models\StudentModel;

class ErpService
{
    const APP_ID_AI = 8;

    /**
     * @param $studentData
     * @param $exchangeType
     * @param int $giftCodeNum
     * @param int $giftCodeUnit
     * @param bool $autoApply
     * @param array $billInfo
     * @param $package
     * @return array [string errorCode, array giftCodes]
     */
    public static function exchangeGiftCode($studentData,
                                            $exchangeType,
                                            $giftCodeNum = 1,
                                            $giftCodeUnit = GiftCodeModel::CODE_TIME_YEAR,
                                            $autoApply = false,
                                            $billInfo = [],
                                            $package)
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
            }

            $student = StudentModel::getById($ret['student_id']);
        }

        if (empty($student)) {
            return ['user_register_fail', null];
        }

        $now = time();
        $operatorId = $billInfo['operator_id'] ?? EmployeeModel::SYSTEM_EMPLOYEE_ID;
        $remarks = $billInfo['remarks'] ?? null;
        $giftCodes = GiftCodeService::batchCreateCode(
            1,
            $giftCodeNum,
            $giftCodeUnit,
            $exchangeType,
            $student['id'],
            GiftCodeModel::CREATE_BY_SYSTEM,
            $remarks,
            $operatorId,
            $now,
            $billInfo
        );

        if (empty($giftCodes)) {
            return ['create_gift_code_fail', null];
        }

        if ($autoApply) {
            StudentServiceForApp::redeemGiftCode($giftCodes[0], $student['id']);
        }

        if (empty($student['first_pay_time'])) {
            StudentModel::updateStudent($student['id'], ['first_pay_time' => $now]);
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

        // 如果是以'_1'结尾的订单号，本地检查是否有不带'_1'的数据
        if (empty($code)) {
            $p = strrpos($billId, '_1');
            if ($p !== false) {
                $billId = substr($billId, 0, $p);
                $code = GiftCodeModel::getByBillId($billId);
            }
        }

        if (empty($code)) {
            return 'invalid_bill_id';
        }

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

        //如果激活码详细表有相应数据，扣减时间具体按照gift_code_detailed这张表的valid_days字段为标准，因为可能会有请假顺延时间的情况
        $giftCodeDetailInfo = GiftCodeDetailedModel::getRecord(['apply_user' => $code['apply_user'], 'gift_code_id' => $code['id'], 'status' => Constants::STATUS_TRUE]);
        if (!empty($giftCodeDetailInfo)) {
            $beforeGiftCodeInfo = GiftCodeDetailedModel::getRecord(['apply_user' => $code['apply_user'], 'id[<]' => $giftCodeDetailInfo['id'], 'ORDER' => ['id' => 'DESC']]);
            if (empty($beforeGiftCodeInfo)) {
                $validNum = $giftCodeDetailInfo['valid_days'] - 1;
            } else {
                $validNum = $giftCodeDetailInfo['valid_days'];
            }
            $validUnits = GiftCodeModel::CODE_TIME_DAY;
        } else {
            $validNum = $code['valid_num'];
            $validUnits = $code['valid_units'];
        }

        if ($code['code_status'] == GiftCodeModel::CODE_STATUS_HAS_REDEEMED) {
            // 已激活的扣除响应时间
            $cnt = StudentService::reduceSubDuration($code['apply_user'], $validNum, $validUnits);
            if (empty($cnt)) { return 'data_error'; }
            //当前激活码之后已激活的从新计算每个激活码的开始&结束时间
            GiftCodeDetailedService::abandonGiftCode($student['id'], $code['id']);
            if (empty($cnt)) {
                return 'gift_code_insert_error';
            }
            //作废激活码，取消所有的请假
            $cnt = GiftCodeDetailedService::cancelLeave($student['id'], $code['id'], StudentLeaveLogModel::CANCEL_OPERATOR_SYSTEM, Constants::STATUS_FALSE);
            if (empty($cnt)) {
                return 'cancel_leave_error';
            }
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

        // 新账号增加时长
        $endTime = strtotime($srcStudent['sub_end_date']);
        $today = strtotime('today');
        // 增加的天数
        $days = intval(($endTime - $today) / 86400);
        //新账号的所属业务线与原账号业务线保持
        $dstStudentUpdate = [
            'update_time'  => $now,
            'serve_app_id'  => $srcStudent['serve_app_id'],
            'has_review_course'  => $srcStudent['has_review_course'],
        ];
        if ($days > 0) {
            // 新账号的时间
            if (empty($dstStudent['sub_end_date'])) {
                $dstEndTime = $today;
                $dstStudentUpdate['sub_start_date'] = date('Ymd', $today);
            } else {
                $dstEndTime = strtotime($dstStudent['sub_end_date']);
            }
            $dstNewEndTime = strtotime("+{$days} day", $dstEndTime);
            $dstStudentUpdate['sub_end_date'] = date('Ymd', $dstNewEndTime);
        }
        //修改新账号数据
        $cnt = StudentModel::updateRecord($dstStudent['id'], $dstStudentUpdate, false);
        if (empty($cnt)) { return 'data_error'; }


        // 就账号删除时长
        $srcStudentUpdate = [
            'sub_start_date' => 0,
            'sub_end_date' => 0,
            'update_time'  => $now,
        ];
        $cnt = StudentModel::updateRecord($srcStudent['id'], $srcStudentUpdate, false);
        if (empty($cnt)) { return 'data_error'; }

        // 所有使用人为原账号的订单，更新购买人为新账号
        GiftCodeModel::batchUpdateRecord(['apply_user' => $dstStudent['id']], ['apply_user' => $srcStudent['id']], false);

        // 所有购买人为原账号的订单，更新购买人为新账号
        GiftCodeModel::batchUpdateRecord(['buyer' => $dstStudent['id']], ['buyer' => $srcStudent['id']], false);

        return null;
    }
}