<?php


namespace App\Services;


use App\Libs\Constants;
use App\Libs\Util;
use App\Models\GiftCodeDetailedModel;
use App\Models\GiftCodeModel;
use App\Models\PackageExtModel;
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
        $date = date('Ymd');
        $time = time();
        $gift = GiftCodeModel::getByCode($code);
        if (empty($gift)) {
            return ['gift_code_error'];
        }

        $student = StudentModelForApp::getStudentInfo($studentID, null);
        if (empty($student)) {
            return ['unknown_student'];
        }
        //计算用户的体验时长
        $trialDays = Util::dateDiff(date('Y-m-d', strtotime($student['trial_start_date'])), date('Y-m-d', strtotime($student['trial_end_date'])));
        //获取用户最后一次正常的激活记录
        $lastGiftCodeDetailed = GiftCodeDetailedModel::getRecord(['apply_user' => $studentID, 'status' => Constants::STATUS_TRUE, 'ORDER' => ['code_end_date' => 'DESC'],]);
        if (empty($lastGiftCodeDetailed)) {
            $codeStartTime = $lastCodeEndTime = $time + 86400 * $trialDays;
        } elseif($lastGiftCodeDetailed['code_end_date'] < $date) {
            $codeStartTime = $lastCodeEndTime = $time;
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
            'create_time' => $time,
            'status' => Constants::STATUS_TRUE
        ];
        $affectRows = GiftCodeDetailedModel::insertRecord($insertData);
        if ($affectRows == 0) {
            return ['insert_gift_code_detailed_fail'];
        }
        return [];
    }

    /**
     * 更改激活码信息
     * @param $studentId
     * @param $giftCodeId
     * @return array
     */
    public static function updateGiftCodeInfo($studentId, $giftCodeId)
    {
        $date = date('Ymd');
        $time = time();
        $affectIdRows = $affectDataRows = Constants::STATUS_TRUE;
        //获取激活码的数据
        $giftCodeDetailedInfo = GiftCodeDetailedModel::getRecord(['gift_code_id' => $giftCodeId, 'apply_user' => $studentId, 'status' => Constants::STATUS_TRUE]);
        if (empty($giftCodeDetailedInfo)) {
            return [];
        }
        //获取用户当前激活码之后的所有数据
        $afterGiftCodeDetailedData = GiftCodeDetailedModel::getRecords(['apply_user' => $studentId, 'id[>=]' => $giftCodeDetailedInfo['id']]);
        if (empty($afterGiftCodeDetailedData)) {
            return [];
        }

        //获取用户当前激活码的前一条数据
        $beforeGiftCodeDetailed = GiftCodeDetailedModel::getRecord(['apply_user' => $studentId, 'status' => Constants::STATUS_TRUE, 'id[<]' => $giftCodeDetailedInfo['id'], 'ORDER' => ['id' => 'DESC']]);
        if (empty($beforeGiftCodeDetailed)) {
            $newCodeStartTime = strtotime($giftCodeDetailedInfo['code_start_date']);
            $lastCodeEndTime = strtotime($giftCodeDetailedInfo['code_start_date']) - 86400;
        } else {
            $newCodeStartTime = strtotime($beforeGiftCodeDetailed['code_end_date']) + 86400;
            $lastCodeEndTime = strtotime($beforeGiftCodeDetailed['code_end_date']);
        }

        //如果激活码开始使用时间大于今天，计算当前激活码已使用天数，并更新数据
        if ($giftCodeDetailedInfo['code_start_date'] < $date) {
            $actualDays = Util::dateDiff($giftCodeDetailedInfo['code_start_date'], $date);
            $affectIdRows = GiftCodeDetailedModel::updateRecord($giftCodeDetailedInfo['id'], ['actual_days' => $actualDays, 'update_time' => $time]);
        }

        //用户当前激活码之后的所有激活码更改为废除
        $delGiftCodeDetailedIds = array_column($afterGiftCodeDetailedData, 'id');
        $affectIdsRows = GiftCodeDetailedModel::batchUpdateRecord(['status' => Constants::STATUS_FALSE, 'update_time' => $time], ['id' => $delGiftCodeDetailedIds]);

        if ($affectIdsRows && $affectIdRows) {
            return [null, $afterGiftCodeDetailedData, $newCodeStartTime, $lastCodeEndTime];
        }
        return ['update_gift_code_error'];
    }


    /**
     * 作废激活码，从新计算用户当前激活之后所有激活码的开始&结束时间，并且入库
     * @param $studentId
     * @param $codeId
     * @return array|int
     */
    public static function abandonGiftCode($studentId, $codeId)
    {
        $time = time();
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
            $timeStr = "+" . $giftCodeDetailed['valid_days'] . "day";

            if (!empty($accountData)) {
                $lastData = array_pop($accountData);
                array_push($accountData, $lastData);
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
                'create_time' => $time,
                'status' => $giftCodeDetailed['status'],
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

    /**
     * 学生请假更改每个激活码的开始&结束时间，并且从新入库
     * @param $studentId
     * @param $giftCodeId
     * @param $leaveDays //请假天数
     * @return bool|int
     */
    public static function studentLeaveGiftCode($studentId, $giftCodeId, $leaveDays)
    {
        $time = time();
        //请假之后所有的激活码，改为废除，并且获取需要重新计算时间的激活码
        list($errorCode, $giftCodeData, $codeStartTime, $lastCodeEndTime) = self::updateGiftCodeInfo($studentId, $giftCodeId);
        if (!empty($errorCode)) {
            return Constants::STATUS_FALSE;
        }

        if (empty($giftCodeData)) {
            return Constants::STATUS_TRUE;
        }

        $accountData = [];
        foreach ($giftCodeData as $giftCodeDetailed) {
            if ($giftCodeDetailed['gift_code_id'] == $giftCodeId)  {
                $giftCodeDetailed['valid_days'] += $leaveDays;
            }

            $timeStr = '+' . $giftCodeDetailed['valid_days'] . 'day';

            if (!empty($accountData)) {
                $lastData = array_pop($accountData);
                array_push($accountData, $lastData);
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
                'create_time' => $time,
                'status' => $giftCodeDetailed['status'],
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

    /**
     * 学生取消请假
     * @param $studentId
     * @param $giftCodeId
     * @param $leaveSurplusDays //请假剩余天数
     * @return bool|int
     */
    public static function studentCancelGiftCode($studentId, $giftCodeId, $leaveSurplusDays)
    {
        $time = time();
        //取消之后所有的激活码，改为废除，并且获取需要重新计算时间的激活码
        list($errorCode, $giftCodeData, $codeStartTime, $lastCodeEndTime) = self::updateGiftCodeInfo($studentId, $giftCodeId);
        if (!empty($errorCode)) {
            return Constants::STATUS_FALSE;
        }

        if (empty($giftCodeData)) {
            return Constants::STATUS_TRUE;
        }

        $accountData = [];
        foreach ($giftCodeData as $giftCodeDetailed) {
            //取消请假的激活码，请假天数减去剩余天数
            if ($giftCodeDetailed['gift_code_id'] == $giftCodeId)  {
                $giftCodeDetailed['valid_days'] -= $leaveSurplusDays;
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
                'create_time' => $time,
                'status' => $giftCodeDetailed['status'],
                'actual_days' => $giftCodeDetailed['actual_days']

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

    /**
     * 取消请假，更新请假记录表
     * @param $studentId
     * @param $codeId
     * @param $cancelOperator //取消操作人，为空说明系统取消
     * @param $cancelOperatorType //取消请假操作人类型 1课管  2用户 3系统（用户退费）
     * @return bool
     */
    public static function cancelLeave($studentId, $codeId, $cancelOperatorType, $cancelOperator = Constants::STATUS_FALSE)
    {
        $time = time();
        $studentLeaveInfo = StudentLeaveLogModel::getRecord(['student_id' => $studentId, 'gift_code_id' => $codeId, 'leave_status' => StudentLeaveLogModel::STUDENT_LEAVE_STATUS_NORMAL, 'ORDER' => ['id' => 'DESC']]);
        if (empty($studentLeaveInfo)) {
            return true;
        }

        //请假开始时间小于当前时间戳，计算实际请假天数
        if ($studentLeaveInfo['start_leave_time'] < $time) {
            $actualDays = Util::dateDiff(date('Y-m-d', $studentLeaveInfo['start_leave_time']), date('Y-m-d', $time));
        } else {
            $actualDays = Constants::STATUS_FALSE;
        }

        $affectStudentLeaveRows = StudentLeaveLogModel::updateRecord($studentLeaveInfo['id'], ['leave_status' => StudentLeaveLogModel::STUDENT_LEAVE_STATUS_CANCEL, 'actual_end_time' => $time, 'actual_days' => $actualDays, 'cancel_time' => $time, 'cancel_operator_type' => $cancelOperatorType, 'cancel_operator' => $cancelOperator]);

        return $affectStudentLeaveRows && $affectStudentLeaveRows > 0;
    }

    /**
     * 判断用户请假的开始时间是否在年卡范围内
     * @param $studentId
     * @param $startLeaveDate
     * @return bool
     */
    public static function startLeaveDateIsYear($studentId, $startLeaveDate)
    {
        $giftCodeId = "";
        $studentYearGiftCodes = GiftCodeDetailedModel::getRecords(['apply_user' => $studentId, 'status' => Constants::STATUS_TRUE, 'package_type' => PackageExtModel::PACKAGE_TYPE_NORMAL]);

        foreach ($studentYearGiftCodes as $studentYearGiftCode) {
            if ($startLeaveDate >= $studentYearGiftCode['code_start_date'] && $startLeaveDate <= $studentYearGiftCode['code_end_date']) {
                $giftCodeId = $studentYearGiftCode['gift_code_id'];
                continue;
            }
        }
        return $giftCodeId;
    }

    /**
     * 获取学生正常激活码的状态
     * @param $studentId
     * @return array
     */
    public static function getStudentLeavePeriod($studentId)
    {
        $studentLeavePeriod = GiftCodeDetailedModel::getRecords(['apply_user' => $studentId, 'status' => Constants::STATUS_TRUE]);
        $date = date('Ymd');
        foreach ($studentLeavePeriod as $item) {
            if ($item['code_start_date'] >= $date) {
                $studentLeavePeriodDate[] = $item;
            } else if ($item['code_start_date'] <= $date && $item['code_end_date'] >= $date) {
                $studentLeavePeriodDate[] = $item;
            }
        }
        return $studentLeavePeriodDate ?? [];
    }


    /**
     *计算每个激活码的开始&结束时间，并且格式化插入数据
     * @param $studentGiftCodeDate
     * @param $studentInfo
     * @param $studentsLastCodeDetailedInfo
     * @param $packageV1Date
     * @param $packageDate
     * @return array
     */
    public static function GiftCodeDataWashing($studentGiftCodeDate, $studentInfo, $studentsLastCodeDetailedInfo, $packageV1Date, $packageDate)
    {
        $batchInsertData = [];
        $codeStartTime = $codeEndTime = $time = time();

        foreach ($studentGiftCodeDate as $item) {
            $studentLastGiftCode = [];
            //如果批量插入的数组是空，说明item是第一条数据，直接调用计算方法
            if (empty($batchInsertData)) {
                list($codeStartTime, $codeEndTime) = self::calculationDate($item['apply_user'], $item['be_active_time'], $studentInfo, $studentsLastCodeDetailedInfo, []);
            }
            //如果批量插入的数组不为空，说明item至少是第二条数据，查看当前item激活码的使用人，是否在批量插入的数组内
            if (!empty($batchInsertData)) {
                foreach ($batchInsertData as $value) {
                    if ($value['apply_user'] == $item['apply_user']) {
                        $studentLastGiftCode[] = $value;
                    }
                }
                list($codeStartTime, $codeEndTime) = self::calculationDate($item['apply_user'], $item['be_active_time'], $studentInfo, $studentsLastCodeDetailedInfo, $studentLastGiftCode);
            }

            $timeStr = '+' . $item['valid_num'] . ' ';
            switch ($item['valid_units']) {
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

            //如果激活码作废，计算激活码真正使用的时间
            if ($item['code_status'] == GiftCodeModel::CODE_STATUS_INVALID && $item['operate_time'] > $codeStartTime) {
                $actualDays = Util::dateDiff(date('Y-m-d', $codeStartTime), date('Y-m-d', $item['operate_time']));
            }

            //兑换package_type
            if ($item['package_v1'] == Constants::STATUS_TRUE && $item['bill_package_id'] != Constants::STATUS_FALSE) {
                $package_type = $packageV1Date[$item['bill_package_id']]['package_type'];
            } elseif($item['package_v1'] == Constants::STATUS_FALSE && $item['bill_package_id'] != Constants::STATUS_FALSE) {
                $package_type = $packageDate[$item['bill_package_id']]['package_type'];
            } else {
                $package_type = Constants::STATUS_FALSE;
            }

            //计算当前激活码开始&结束时间差，以天为单位
            $validDays = Util::dateBetweenDays(date('Y-m-d', $codeStartTime), date('Y-m-d', strtotime($timeStr, $codeEndTime)));
            $batchInsertData[] = [
                'gift_code_id' => $item['id'],
                'apply_user' => $item['apply_user'],
                'code_start_date' => date('Ymd', $codeStartTime),
                'code_end_date' => date('Ymd', strtotime($timeStr, $codeEndTime)),
                'package_type' => $package_type,
                'valid_days' => $validDays,
                'create_time' => $time,
                'actual_days' => !empty($actualDays) ? $actualDays : Constants::STATUS_FALSE,
                'status' => $item['code_status'] == GiftCodeModel::CODE_STATUS_HAS_REDEEMED ? Constants::STATUS_TRUE : Constants::STATUS_FALSE
            ];

        }
        return $batchInsertData;

    }

    /**
     * 根据激活码使用人，和激活码的激活时间计算每个激活码的开始&结束时间
     * @param $applyUser
     * @param $beActiveTime //激活时间
     * @param array $studentLastGiftCode
     * @param $studentInfo
     * @param $studentsLastCodeDetailedInfo
     * @return array
     */
    public static function calculationDate($applyUser, $beActiveTime, $studentInfo, $studentsLastCodeDetailedInfo, $studentLastGiftCode = [])
    {
        //如果当前使用人的批量插入数据不为空，取最后一条激活码的插入数据
        if (!empty($studentLastGiftCode)) {
            $lastGiftCodeInfo = array_pop($studentLastGiftCode);
            //查看上一条激活码的状态，如果激活码状态为正常，假设激活码开始时间，最后一条激活码的结束时间+1天
            if ($lastGiftCodeInfo['status'] == Constants::STATUS_TRUE) {
                $nextStartTime = strtotime($lastGiftCodeInfo['code_end_date']) + 86400;
            } else {
                //如果是作废，计算真正使用的时间，并且计算下一条激活码的开始时间
                $nextStartTime = strtotime($lastGiftCodeInfo['code_start_date']) + $lastGiftCodeInfo['actual_days'] * 86400;
            }

            //如果激活码的激活时间小于开始时间，则激活码真正的开始时间为：最后一条激活码的结束时间+1天
            if (date('Ymd', $beActiveTime) < date('Ymd', $nextStartTime)) {
                $codeStartTime = $nextStartTime;
                $codeEndTime = $nextStartTime - 86400;
            } else {
                //否则激活码的开始时间为：激活码真正激活的时间
                $codeStartTime = $beActiveTime;
                $codeEndTime = $beActiveTime - 86400;
            }
            return [$codeStartTime, $codeEndTime];
        }

        //如果当前使用人的批量插入数据为空，取最后一条激活码的详细数据
        if (empty($studentsLastCodeDetailedInfo[$applyUser])) {
            $lastGiftCodeInfo = [];
        } else {
            $lastGiftCodeInfo = $studentsLastCodeDetailedInfo[$applyUser];
        }

        if (empty($lastGiftCodeInfo)) {
            //如果最后一条激活码的详细数据为空，获取用户的信息
            $studentInfo = $studentInfo[$applyUser];
            //计算用户的体验时长
            if (empty($studentInfo['trial_start_date']) || empty($studentInfo['trial_end_date'])) {
                //用户第一个激活码真正的开始时间 = 开始时间+体验时长
                $giftCodeFirstTime = strtotime($studentInfo['sub_start_date']);
            } else {
                $trialDays = Util::dateDiff(date('Y-m-d', strtotime($studentInfo['trial_start_date'])), date('Y-m-d', strtotime($studentInfo['trial_end_date'])));
                $timeStr = '+' . $trialDays . 'day';
                $giftCodeFirstTime = strtotime($timeStr, strtotime($studentInfo['sub_start_date'])) + 86400;
            }

            //如果激活码的激活时间小于开始时间，以开始时间为标准，否则以激活时间为标准
            if (date('Ymd', $beActiveTime) < date('Ymd', $giftCodeFirstTime)) {
                $codeStartTime = $codeEndTime = $giftCodeFirstTime;
            } else {
                $codeStartTime = $codeEndTime = $beActiveTime;
            }
            return [$codeStartTime, $codeEndTime];
        }
        if (!empty($lastGiftCodeInfo)){
            //最后一条激活码的详细数据不为空，并且激活码的状态为正常，假设激活码的开始时间为：最后一条激活码的结束时间+1天
            if ($lastGiftCodeInfo['status'] == Constants::STATUS_TRUE) {
                $nextStartTime = strtotime($lastGiftCodeInfo['code_end_date']) + 86400;
            } else {
                //否则激活码的状态为废除，假设激活码的开始时间为：最后一条激活码的开始时间+使用天数+1天
                $nextStartTime = strtotime($lastGiftCodeInfo['code_start_date']) + $lastGiftCodeInfo['actual_days'] * 86400;
            }
            //如果激活码的激活时间小于开始时间，则以开始时间为标准，否则激活时间以激活时间为标准
            if (date('Ymd', $beActiveTime) < date('Ymd', $nextStartTime)) {
                $codeStartTime = $nextStartTime;
                $codeEndTime = $nextStartTime - 86400;
            } else {
                $codeStartTime = $beActiveTime;
                $codeEndTime = $beActiveTime - 86400;
            }
            return [$codeStartTime, $codeEndTime];
        }
        return ['', ''];
    }


    public static function GiftCodeEndDateVerification($giftCodeDetailedInfo, $studentInfo)
    {
        $insertDate = [];
        foreach ($giftCodeDetailedInfo as $giftCodeDetailed) {
            if ($giftCodeDetailed['code_end_date'] == $studentInfo[$giftCodeDetailed['apply_user']]['sub_end_date']) {
                continue;
            } else {
                $batchInsertData = self::calculationStudentDate($giftCodeDetailed['apply_user'], $giftCodeDetailed['code_end_date'], $studentInfo[$giftCodeDetailed['apply_user']]['sub_end_date'], $giftCodeDetailed['id']);
            }
            $insertDate = array_merge($insertDate, $batchInsertData);
        }
        return $insertDate ?? [];
    }

    /**
     * 用户激活码不正确的，进行数据清洗，此方法只会用一次
     * @param $studentId
     * @param $codeEndDate //最后一条正常激活码的结束时间
     * @param $subEndDate //学生年卡结束时间
     * @param $lastNormalId
     * @return array|bool
     */
    public static function calculationStudentDate($studentId, $codeEndDate, $subEndDate, $lastNormalId)
    {
        $subEndTime = strtotime($subEndDate);
        $codeEndTime = strtotime($codeEndDate);
        $batchInsertData = [];
        $time = time();
        //获取用户最后一条正常激活码之前的数据
        $studentNormalDate = GiftCodeDetailedModel::getRecords(['apply_user' => $studentId, 'status' => Constants::STATUS_TRUE, 'id[<=]' => $lastNormalId, 'ORDER' => ['id' => 'DESC']]);
        //获取用户最后一条正常激活码之后的数据
        $studentAbolishDate = GiftCodeDetailedModel::getRecords(['apply_user' => $studentId, 'id[>]' => $lastNormalId, 'ORDER' => ['id' => 'ASC']]);
        GiftCodeDetailedModel::batchUpdateRecord(['status' => Constants::STATUS_FALSE,'update_time' => time()], ['apply_user' => $studentId]);
        if ($codeEndTime > $subEndTime) {
            $diffDay = Util::dateDiff(date('Y-m-d', $codeEndTime), date('Y-m-d', $subEndTime));
            $diffTimeStr = "-" . $diffDay . 'day';
        } else {
            $diffDay = Util::dateDiff(date('Y-m-d', $subEndTime), date('Y-m-d', $codeEndTime));
            $diffTimeStr = "+" . $diffDay . 'day';
        }

        foreach ($studentNormalDate as $item) {
            if ($item['id'] == $lastNormalId) {
                $codeEndTime = strtotime($subEndDate);
                $codeStartTime = $codeEndTime - ($item['valid_days'] - 1) * 86400;
            } else {
                $codeEndTime = strtotime($diffTimeStr, strtotime($item['code_end_date']));
                $timeStr = "-" . $item['valid_days'] . 'day';
                $codeStartTime = strtotime($timeStr, $codeEndTime) + 86400;
            }

            $batchInsertData[] = [
                'gift_code_id' => $item['gift_code_id'],
                'apply_user' => $item['apply_user'],
                'code_start_date' => date('Ymd', $codeStartTime),
                'code_end_date' => date('Ymd', $codeEndTime),
                'package_type' => $item['package_type'],
                'valid_days' => $item['valid_days'],
                'create_time' => $time,
                'actual_days' => $item['actual_days'],
                'status' => $item['status']
            ];
        }

        $batchInsertData = array_reverse($batchInsertData);
        if (empty($studentAbolishDate)) {
            return $batchInsertData;
        }
        foreach ($studentAbolishDate as $item) {
            $timeStr = '+' . $item['valid_days'] . 'day';
            $lastGiftCode = array_pop($batchInsertData);
            array_push($batchInsertData, $lastGiftCode);
            $codeStartTime = strtotime($lastGiftCode['code_end_date']) + 86400;
            $codeEndTime = strtotime($timeStr, strtotime($lastGiftCode['code_end_date']));
            $batchInsertData[] = [
                'gift_code_id' => $item['gift_code_id'],
                'apply_user' => $item['apply_user'],
                'code_start_date' => date('Ymd', $codeStartTime),
                'code_end_date' => date('Ymd', $codeEndTime),
                'package_type' => $item['package_type'],
                'valid_days' => $item['valid_days'],
                'create_time' => $time,
                'actual_days' => $item['actual_days'],
                'status' => $item['status']
            ];
        }

        return $batchInsertData;
    }
}