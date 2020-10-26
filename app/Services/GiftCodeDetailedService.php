<?php


namespace App\Services;


use App\Libs\Constants;
use App\Libs\Util;
use App\Models\GiftCodeDetailedModel;
use App\Models\GiftCodeModel;
use App\Models\StudentLeaveLogModel;
use App\Models\StudentModelForApp;

class GiftCodeDetailedService
{
    /**
     * 生成用户激活码的开始时间结束时间
     * @param $code
     * @param $studentID
     * @param $packageType
     * @return bool|string[]
     */
    public static function CreateGiftCodeDetailed($code, $studentID, $packageType)
    {
        $gift = GiftCodeModel::getByCode($code);
        if (empty($gift)) {
            return ['gift_code_error'];
        }

        $student = StudentModelForApp::getStudentInfo($studentID, null);
        if (empty($student)) {
            return ['unknown_student'];
        }
        //获取用户最后一次正常的激活记录
        $lastGiftCodeDetailed = GiftCodeDetailedModel::getRecord(['apply_user' => $studentID, 'status' => Constants::STATUS_TRUE, 'ORDER' => ['code_end_date' => 'DESC'],]);
        if (empty($lastGiftCodeDetailed)) {
            $codeStartTime = $lastCodeEndTime = time();
        } else {
            $codeStartTime = strtotime($lastGiftCodeDetailed['code_end_date']) + 86400;
            $lastCodeEndTime = strtotime($lastGiftCodeDetailed['code_end_date']);
        }

        $timeStr = '+' . $gift['valid_num'] . ' ';
        switch ($gift['valid_units']) {
            case GiftCodeModel::CODE_TIME_DAY:
                $timeStr .= 'day';
                break;
            case GiftCodeModel::CODE_TIME_MONTH:
                $timeStr .= 'month';
                break;
            case GiftCodeModel::CODE_TIME_YEAR:
                $timeStr .= 'year';
                break;
            default:
                $timeStr .= 'day';
        }

        //计算激活码 开始&结束时间差
        $codeStartDate = date("Ymd", $codeStartTime);
        $codeEndDate = date('Ymd', strtotime($timeStr, $lastCodeEndTime));
        $validDays = Util::dateBetweenDays($codeStartDate, $codeEndDate);
        $insertData = [
            'gift_code_id' => $gift['id'],
            'apply_user' => $studentID,
            'code_start_date' => $codeStartDate,
            'code_end_date' => $codeEndDate,
            'package_type' => !empty($packageType) ? $packageType : Constants::STATUS_FALSE, //赠送时长没有packageType，跟数据库保持一致默认0
            'valid_days' => $validDays,
            'create_time' => time(),
            'status' => Constants::STATUS_TRUE
        ];
        $affectRows = GiftCodeDetailedModel::insertRecord($insertData);
        if ($affectRows == 0) {
            return ['insert_gift_code_detailed_fail'];
        }
        return true;
    }

    /**
     * 更改激活码信息
     * @param $studentId
     * @param $giftCodeId
     * @return array
     */
    public static function updateGiftCodeInfo($studentId, $giftCodeId)
    {
        $affectIdRows = $affectDataRows = Constants::STATUS_TRUE;
        //获取激活码的数据
        $giftCodeDetailedInfo = GiftCodeDetailedModel::getRecord(['gift_code_id' => $giftCodeId, 'apply_user' => $studentId, 'status' => Constants::STATUS_TRUE]);
        if (empty($giftCodeDetailedInfo)) {
            return [];
        }
        //获取用户当前激活码之后的所有数据
        $afterGiftCodeDetailedData = GiftCodeDetailedModel::getRecords(['apply_user' => $studentId, 'id[>=]' => $giftCodeDetailedInfo['id'], 'status' => Constants::STATUS_TRUE]);
        if (empty($afterGiftCodeDetailedData)) {
            return [];
        }

        //获取用户当前激活码的前一条数据
        $beforeGiftCodeDetailed = GiftCodeDetailedModel::getRecord(['apply_user' => $studentId, 'status' => Constants::STATUS_TRUE, 'id[<]' => $giftCodeDetailedInfo['id'], 'ORDER' => ['id' => 'DESC']]);
        if (empty($beforeGiftCodeDetailed)) {
            $newCodeStartTime = $lastCodeEndTime = time();
        } else {
            $newCodeStartTime = strtotime($beforeGiftCodeDetailed['code_end_date']) + 86400;
            $lastCodeEndTime = strtotime($beforeGiftCodeDetailed['code_end_date']);
        }

        //如果激活码开始使用时间大于今天，计算当前激活码已使用天数，并更新数据
        if ($giftCodeDetailedInfo['code_start_date'] < date('Ymd')) {
            $actualDays = Util::dateDiff($giftCodeDetailedInfo['code_start_date'], date('Ymd'));
            $affectIdRows = GiftCodeDetailedModel::updateRecord($giftCodeDetailedInfo['id'],['actual_days' => $actualDays, 'update_time' => time()]);
        }

        //用户当前激活码之后的所有激活码更改为废除
        $delGiftCodeDetailedIds =  array_column($afterGiftCodeDetailedData,  'id');
        $affectIdsRows = GiftCodeDetailedModel::batchUpdateRecord(['status' => Constants::STATUS_FALSE, 'update_time' => time()], ['id' => $delGiftCodeDetailedIds]);

        if ($affectIdsRows && $affectIdRows) {
            return [null, $afterGiftCodeDetailedData, $newCodeStartTime, $lastCodeEndTime];
        }
        return ['update_gift_code_error'];
    }

    /**
     * 取消请假
     * @param $studentId
     * @param $codeId
     * @param $cancelOperator //取消操作人，为空说明系统取消
     * @param $cancelOperatorType //取消请假操作人类型 1课管  2用户 3系统（用户退费）
     * @return bool
     */
    public static function cancelLeave($studentId, $codeId, $cancelOperatorType, $cancelOperator = Constants::STATUS_FALSE)
    {
        $studentLeaveInfo = StudentLeaveLogModel::getRecord(['student_id' => $studentId, 'gift_code_id' => $codeId, 'leave_status' => StudentLeaveLogModel::STUDENT_LEAVE_STATUS_NORMAL]);
        if (empty($studentLeaveInfo)) return true;

        //请假开始时间小于当前时间戳，计算实际请假天数
        if ($studentLeaveInfo['start_leave_time'] < time()) {
            $actualDays = Util::dateDiff(strtotime('Y-m-d', $studentLeaveInfo['start_leave_time']), strtotime('Y-m-d', time()));
        } else {
            $actualDays = Constants::STATUS_FALSE;
        }

        $affectStudentLeaveRows = StudentLeaveLogModel::updateRecord($studentLeaveInfo['id'], ['leave_status' => StudentLeaveLogModel::STUDENT_LEAVE_STATUS_CANCEL, 'actual_end_time' => time(), 'actual_days' => $actualDays, 'cancel_time' => time(), 'cancel_operator_type' => $cancelOperatorType, 'cancel_operator' => $cancelOperator]);

        return $affectStudentLeaveRows && $affectStudentLeaveRows > 0;
    }


    /**
     * 作废激活码，从新计算用户当前激活之后所有激活码的开始&结束时间，并且入库
     * @param $studentId
     * @param $codeId
     * @return array|int
     */
    public static function abandonGiftCode($studentId, $codeId)
    {
        //退费之后所有的激活码，改为废除，并且获取需要重新计算时间的激活码
        list($errorCode, $giftCodeData, $codeStartTime, $lastCodeEndTime) = self::updateGiftCodeInfo($studentId, $codeId);
        if (!empty($errorCode)) {
            return Constants::STATUS_FALSE;
        }

        if (empty($giftCodeData)) {
            return Constants::STATUS_TRUE;
        }

        $accountData = [];
        foreach ($giftCodeData as $giftCodeDetailed) {
            if ($giftCodeDetailed['gift_code_id'] == $codeId) {
                continue;
            }
            $timeStr = '+' . $giftCodeDetailed['valid_days'] . 'day';

            if (!empty($accountData)) {
                $lastData = array_pop($accountData);
                array_push($accountData,$lastData);
                $codeStartTime = strtotime($lastData['code_end_date']) + 86400;
                $lastCodeEndTime = strtotime($lastData['code_end_date']);
            }

            $account = [
                'gift_code_id' => $giftCodeDetailed['gift_code_id'],
                'apply_user' => $giftCodeDetailed['apply_user'],
                'code_start_date' => date('Ymd', $codeStartTime),
                'code_end_date' => date('Ymd', strtotime($timeStr, $lastCodeEndTime)),
                'package_type' => $giftCodeDetailed['package_type'],
                'valid_days' => $giftCodeDetailed['valid_days'],
                'create_time' => time(),
                'status' => Constants::STATUS_TRUE,
            ];
            $accountData[] = $account;
        }

        if (!empty($accountData)) {
            $affectDataRows = GiftCodeDetailedModel::batchInsert($accountData);
        } else {
            $affectDataRows = Constants::STATUS_TRUE;
        }
        return $affectDataRows;
    }

}