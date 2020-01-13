<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/31
 * Time: 11:09 AM
 */

namespace App\Services;


use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\EmployeeModel;
use App\Models\GiftCodeModel;
use App\Models\StudentModel;

class ErpService
{
    const APP_ID_AI = 8;

    /**
     * @param $studentData
     * @param $exchangeType
     * @param $erpBillId
     * @param $erpBillAmount
     * @param $erpBillAppId
     * @param $erpBillPackageId
     * @param int $giftCodeNum
     * @param int $giftCodeUnit
     * @param bool $autoApply
     * @return array [string errorCode, array giftCodes]
     */
    public static function exchangeGiftCode($studentData,
                                            $exchangeType,
                                            $erpBillId,
                                            $erpBillAmount,
                                            $erpBillAppId,
                                            $erpBillPackageId,
                                            $giftCodeNum = 1,
                                            $giftCodeUnit = GiftCodeModel::CODE_TIME_YEAR,
                                            $autoApply = false)
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

        // 不发货黑名单
        $blackList = [
            18068420492,
            13811342870,
            13596318897,
            13599121314,
            13401681258,
            13507235678,
            13501999476,
            13911666502,
            15077203445,
            13810699631,
            13542604447,
            15810995993,
            18500034605,
            15607339985,
            13944188175,
            18924893169,
            18105311023,
            13564211341,
            18616878392,
            13823642658,
            13043512009,
            18958269916,
            13966388982,
            18695794108,
            18982593384,
            13599121314,
            13590433810,
            13882996468,
            13929577750,
            18017305779,
            15800462296,
            17317898461,
            13816598567,
            13918680650,
            13671718067,
            15547232680,
            18657392464,
            13061782939,
            13141336533,
            13777976803,
            13636367540,
            13902561662,
            13567550919,
            13681933847,
            13957588180,
            13805842164,
            17705456898,
            13913931524,
            17764585538,
            13918831520,
            13921606916,
            13681915986,
            13352276907,
            18605803677,
            13810357147,
            13765146999,
            13601188129,
            13761145566,
            13917427285,
            15026922520,
            13706065360,
            13916038183,
            13916954731,
            18670610555,
            13774310274,
            18621506222,
            13609888688,
            13575716450,
            18657130722,
            13816834063,
            18665325922,
            13918287977,
            13524491850,
            13621645603,
            18515066291,
            13820125629,
            13918265863,
            13804111027,
            17898833461,
            13810470466,
            13961866761,
            15802117296,
            13761425572,
            18221693762,
            13651891880,
            13901869281,
            15146230158,
            13456399618,
            13817601166,
            15019432025,
            13918845964,
            13808985305,
            15312231337,
            13701891603,
            13681849174,
            13918022817,
            13916862507,
            13585858374,
            13361808650,
            13816947847,
            18621503129,
            15000666106,
            18616778568,
            15800956625,
            18701936290,
            13816369002,
            13718505721,
            18665365229,
            13641301604,
            13817389840,
            17721473625,
            13901783492,
            13296571028,
            18717770504,
            13918890697,
            13611737020,
            13601601676,
            13917836590,
            15802192727,
            13770911311,
            13601711922,
            13917643130,
            13816770203,
            13982207765,
            17705456898,
            13729163032,
            13925911668,
            18310333155,
            18952065188,
            13719280967,
            13764550599,
            13929527052,
            15835443657,
            18511997644,
            13585631921
        ];
        if (in_array($student['mobile'], $blackList)) {
            SimpleLogger::info("user in black list", ['mobile' => $student['mobile']]);
            return [null, []];
        }

        $orgId = DictConstants::get(DictConstants::SPECIAL_ORG_ID, 'panda');
        StudentService::bindOrg($orgId, $student['id']);

        $now = time();
        $giftCodes = GiftCodeService::batchCreateCode(
            1,
            $giftCodeNum,
            $giftCodeUnit,
            $exchangeType,
            $student['id'],
            GiftCodeModel::CREATE_BY_SYSTEM,
            NULL,
            EmployeeModel::SYSTEM_EMPLOYEE_ID,
            $now,
            $erpBillId,
            $erpBillAmount,
            $erpBillAppId,
            $erpBillPackageId);

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

        // 新账号增加时长
        $endTime = strtotime($srcStudent['sub_end_date']);
        $today = strtotime('today');
        // 增加的天数
        $days = intval(($endTime - $today) / 86400);
        if ($days > 0) {
            // 新账号的时间
            $dstStudentUpdate = ['update_time'  => $now];
            if (empty($dstStudent['sub_end_date'])) {
                $dstEndTime = $today;
                $dstStudentUpdate['sub_start_date'] = date('Ymd', $today);
            } else {
                $dstEndTime = strtotime($dstStudent['sub_end_date']);
            }
            $dstNewEndTime = strtotime("+{$days} day", $dstEndTime);
            $dstStudentUpdate['sub_end_date'] = date('Ymd', $dstNewEndTime);

            $cnt = StudentModel::updateRecord($dstStudent['id'], $dstStudentUpdate, false);
            if (empty($cnt)) { return 'data_error'; }
        }

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