<?php


namespace App\Services;


use App\Libs\Constants;
use App\Libs\Util;
use App\Models\GiftCodeDetailedModel;
use App\Models\ReviewCourseModel;
use App\Models\StudentLeaveLogModel;
use App\Models\StudentModel;
use App\Models\StudentModelForApp;

class StudentLeaveLogService
{
    /**
     * 获取请假列表
     * @param $studentId
     * @return array
     */
    public static function getStudentLeaveLogList($studentId)
    {
        $studentLeaveLogList = StudentLeaveLogModel::getStudentLeaveList($studentId);
        if (empty($studentLeaveLogList)) {
            return [];
        }
        array_walk($studentLeaveLogList, function (&$studentLeaveLog) {
            if ($studentLeaveLog['leave_status'] == StudentLeaveLogModel::STUDENT_LEAVE_STATUS_NORMAL && $studentLeaveLog['end_leave_time'] < time()) $studentLeaveLog['leave_status'] = (string)StudentLeaveLogModel::STUDENT_LEAVE_STATUS_OVER;
            $studentLeaveLog['leave_time'] = !empty($studentLeaveLog['leave_time']) ? date('Y-m-d H:i:s', $studentLeaveLog['leave_time']) : '';
            $studentLeaveLog['start_leave_time'] = !empty($studentLeaveLog['start_leave_time']) ? date('Y-m-d', $studentLeaveLog['start_leave_time']) : '';
            $studentLeaveLog['end_leave_time'] = !empty($studentLeaveLog['end_leave_time']) ? date('Y-m-d', $studentLeaveLog['end_leave_time']) : '';
            $studentLeaveLog['actual_end_time'] = !empty($studentLeaveLog['actual_end_time']) ? date('Y-m-d', $studentLeaveLog['actual_end_time']) : '';
            $studentLeaveLog['cancel_time'] = !empty($studentLeaveLog['cancel_time']) ? date('Y-m-d H:i:s', $studentLeaveLog['cancel_time']) : '';
        });
        return $studentLeaveLogList;
    }

    /**
     * 学生请假
     * @param $studentId
     * @param $leaveOperator
     * @param $startLeaveDate
     * @param $endLeaveDate
     * @param $leaveDays
     * @return bool|string
     */
    public static function studentLeave($studentId, $leaveOperator, $startLeaveDate, $endLeaveDate, $leaveDays)
    {
        $student = StudentModelForApp::getStudentInfo($studentId, null);
        if (empty($student)) {
            return 'unknown_user';
        }
        //检查用户是否有正常的请假数据
        $studentLeave = StudentLeaveLogModel::getRecords(['student_id' => $studentId, 'leave_status' => StudentLeaveLogModel::STUDENT_LEAVE_STATUS_NORMAL, 'end_leave_time[>]' => time()]);
        if (!empty($studentLeave)) {
            return 'please_complete_or_cancel_the_last_leave';
        }
        //请假开始时间不可以小于当前时间
        if ($startLeaveDate < date('Ymd')) {
            return 'leave_time_cannot_be_less_than_today';
        }

        //查找请假开始时间是否在年卡内
        $giftCodeId = GiftCodeDetailedService::startLeaveDateIsYear($studentId, $startLeaveDate);
        if (empty($giftCodeId)) {
            return 'leave_time_is_not_within_of_the_year_card';
        }

        //判断前端给的请假天数是否正确
        $dateDiff = Util::dateBetweenDays($startLeaveDate, $endLeaveDate);
        if ($dateDiff != $leaveDays) {
            return 'leave_days_error';
        }

        //计算请假之后每个激活码的开始&结束时间
        $studentLeaveGiftCode = GiftCodeDetailedService::studentLeaveGiftCode($studentId, $giftCodeId, $leaveDays);
        if (empty($studentLeaveGiftCode)) {
            return 'gift_code_time_calculation_error';
        }

        //年卡结束时间更改
        $subEndDate = date('Ymd', strtotime('+' . $leaveDays . 'day', strtotime($student['sub_end_date'])));
        $affectRows = StudentModel::updateRecord($student['id'], ['sub_end_date' => $subEndDate, 'update_time' => time()]);
        if (empty($affectRows)) {
            return 'update_student_sub_end_date_error';
        }

        //请假记录入库
        $data = [
           'gift_code_id' => $giftCodeId,
            'student_id' => $studentId,
            'leave_operator' => $leaveOperator,
            'leave_time' => time(),
            'start_leave_time' => strtotime($startLeaveDate),
            'end_leave_time' => strtotime($endLeaveDate),
            'leave_days' => $leaveDays,
            'leave_status' => StudentLeaveLogModel::STUDENT_LEAVE_STATUS_NORMAL
        ];
        $affectRows = StudentLeaveLogModel::insertRecord($data);
        if (empty($affectRows)) {
            return 'student_leave_error';
        }
    }

    /**
     * 课管取消请假
     * @param $id
     * @param $cancelOperator
     * @return string
     */
    public static function cancelLeave($id, $cancelOperator)
    {
        $cancelLeaveDate = StudentLeaveLogModel::getRecord(['id' => $id, 'leave_status' => StudentLeaveLogModel::STUDENT_LEAVE_STATUS_NORMAL]);
        if (empty($cancelLeaveDate)) {
            return "record_not_found";
        }
        //如果请假结束时间小于当前时间，说明请假已过期，不可以在取消请假
        if (date('Ymd', $cancelLeaveDate['end_leave_time']) < date('Ymd')) {
            return "leave_has_ended_you_can't_cancel_the_leave";
        }

        //如果当前时间大于请假开始的的时间， 计算用户剩余请假天数
        if (date('Y-m-d') > date('Y-m-d', $cancelLeaveDate['start_leave_time'])) {
            $leaveSurplusDays = Util::dateDiff(date('Y-m-d'), date('Y-m-d', $cancelLeaveDate['end_leave_time']));
        } else {
            $leaveSurplusDays = $cancelLeaveDate['leave_days'];
        }

        //取消请假，把取消请假之后的激活码全部废除，并且从新计算这些激活码的开始&结束时间，并且入库
        $affectRows = GiftCodeDetailedService::studentCancelGiftCode($cancelLeaveDate['student_id'], $cancelLeaveDate['gift_code_id'], $leaveSurplusDays);
        if (empty($affectRows)) {
            return 'gift_code_time_calculation_error';
        }

        //更新请假记录，请假状态为废除
        $affectRows = GiftCodeDetailedService::cancelLeave($cancelLeaveDate['student_id'], $cancelLeaveDate['gift_code_id'], StudentLeaveLogModel::CANCEL_OPERATOR_COURSE, $cancelOperator);
        if (empty($affectRows)) {
            return 'cancel_leave_error';
        }
        //年卡结束时间更改
        $student = StudentModelForApp::getStudentInfo($cancelLeaveDate['student_id'], null);
        $subEndDate = date('Ymd', strtotime('-' . $leaveSurplusDays . 'day', strtotime($student['sub_end_date'])));
        $affectRows = StudentModel::updateRecord($student['id'], ['sub_end_date' => $subEndDate, 'update_time' => time()]);
        if (empty($affectRows)) {
            return 'update_student_sub_end_date_error';
        }

    }

    /**
     * 学生是否可以请假
     * @param $studentId
     * @return bool
     */
    public static function studentLeaveStatus($studentId)
    {
        $leaveStatus = true;
        //用户是否年卡用户
        $student = StudentModelForApp::getStudentInfo($studentId, null);
        if (empty($student) || $student['has_review_course'] != ReviewCourseModel::REVIEW_COURSE_1980) {
            $leaveStatus = false;
        }

        //检查用户是否有进行中的请假
        $studentLeave = StudentLeaveLogModel::getRecord(['student_id' => $studentId, 'leave_status' => StudentLeaveLogModel::STUDENT_LEAVE_STATUS_NORMAL, 'end_leave_time[>]' => time()]);
        if (!empty($studentLeave)) {
            $leaveStatus = false;
        }

        //用户是否在年卡有效期，这个不根据student那张表sub_end_date,因为sub_end_date包含体验、赠送时长
        $validityOfAnnualPass = GiftCodeDetailedModel::getRecord(['apply_user' => $studentId, 'package_type' => ReviewCourseModel::REVIEW_COURSE_1980, 'status' => Constants::STATUS_TRUE, 'ORDER' => ['id' => 'DESC']]);
        if (empty($validityOfAnnualPass) || $validityOfAnnualPass['code_end_date'] < date('Ymd')) {
            $leaveStatus = false;
        }
        return $leaveStatus;

    }

    /**
     * 学生取消请假
     * @param $studentId
     * @return string
     */
    public static function studentCancelLeave($studentId)
    {
        $leaveInfo = StudentLeaveLogModel::getRecord(['student_id' => $studentId, 'leave_status' => StudentLeaveLogModel::STUDENT_LEAVE_STATUS_NORMAL, 'ORDER' => ['id' => 'DESC']]);
        if (empty($leaveInfo)) {
            return false;
        }

        return self::cancelLeave($leaveInfo['id'], $studentId, StudentLeaveLogModel::CANCEL_OPERATOR_STUDENT);
    }

    /**
     * 学生是否请假中
     * @param $studentId
     * @return bool
     */
    public static function getLeaveStatus($studentId)
    {
        $leaveStatus = true;
        //检查用户是否有进行中的请假
        $studentLeave = StudentLeaveLogModel::getRecord(['student_id' => $studentId, 'leave_status' => StudentLeaveLogModel::STUDENT_LEAVE_STATUS_NORMAL, 'end_leave_time[>]' => time()]);
        if (!empty($studentLeave)) {
            $leaveStatus = false;
        }
        return $leaveStatus;
    }

}