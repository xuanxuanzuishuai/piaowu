<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 18/7/11
 * Time: 上午10:07
 */

namespace App\Services;

use App\Models\CallCenterLogModel;
use App\Models\EmployeeModel;
use App\Models\EmployeeSeatModel;

class CallCenterRLLogService extends CallCenterLogService
{
    // event_type
    const CALLBACK_CALLOUT_RINGING = 'rl_outcall_ringing';
    const CALLBACK_CALLOUT_COMPLETE = 'rl_outcall_complete';

    /**
     * 接听状态
     * 对应容联外呼接口 State 字段
     * dealing（已接）
     * notDeal（振铃未接听）
     * leak（ivr放弃）
     * queueLeak（排队放弃）
     * blackList（黑名单）
     * voicemail（留言）
     * limit（并发限制）
     */
    const CALLOUT_STATE_DEALING = 'dealing';
    const CALLOUT_STATE_NOT_DEAL = 'notDeal';
    const CALLOUT_STATE_LEAK = 'leak';
    const CALLOUT_STATE_QUEUE_LEAK = 'queueLeak';
    const CALLOUT_STATE_BLACKLIST = 'blackList';
    const CALLOUT_STATE_VOICE_EMAIL = 'voicemail';
    const CALLOUT_STATE_LIMIT = 'limit';

    /**
     * 外呼响铃
     * @param $params
     * @return bool
     */
    public static function outCallRinging($params)
    {
        $params = self::formatCallParams($params);
        if($params == false){
            return false;
        }
        self::formatRingParams($params);
        return self::logCallRingTime();
    }

    /**
     * 外呼挂机
     * @param $params
     * @return bool
     */
    public static function outCallComplete($params)
    {
        $params = self::formatCallParams($params);
        if($params == false){
            return false;
        }
        self::formatEndParams($params);
        return self::logCallEndTime();
    }

    /**
     * 格式化呼叫参数
     * @param $params
     * @return mixed
     */
    public static function formatCallParams($params)
    {
        if(empty($params) || empty($params['CallSheetID'])){
            return false;
        }
        $params['call_type'] = CallCenterLogModel::CALL_TYPE_OUT;
        $params['seat_type'] = CallCenterLogModel::SEAT_RONGLIAN;
        return $params;
    }

    /**
     * 获取容联坐席类型
     * @return int
     */
    public static function getSeatType()
    {
        return EmployeeSeatModel::SEAT_RONGLIAN;
    }

    /**
     * 根据坐席获取用户id
     * @param $seatId
     * @return int|mixed
     */
    public static function getUserId($seatId)
    {
        $seatType = self::getSeatType();
        return EmployeeSeatModel::getUserId($seatId, $seatType);
    }

    /**
     * 格式化响铃参数
     * @param $params
     * @return mixed
     */
    public static function formatRingParams($params)
    {
        $data = [];
        $data['unique_id'] = $params['CallSheetID'];
        $data['call_type'] = $params['call_type'];
        $data['seat_type'] = $params['seat_type'];
        $data['seat_id'] = isset($params['Agent']) ? (int)$params['Agent'] : 0;
        //小号外呼用RealCalled  坐席外呼用CalledNo
        // $data['customer_number'] = isset($params['CalledNo']) ? $params['CalledNo'] : '';
        $data['customer_number'] = isset($params['RealCalled']) ? $params['RealCalled'] : '';
        $data['create_time'] = time();
        $data['ring_time'] = isset($params['RingingDate']) ? strtotime($params['RingingDate']) : 0;

        //获取用户(CC)id
        if (!empty($data['seat_id'])) {
            $data['employee_id'] = self::getUserId($data['seat_id']);
        }
        //获取客户id
        if (!empty($data['customer_number'])) {
            $data['student_id'] = StudentService::getStudentIdByMobile($data['customer_number']);
        }
        self::setRingParams($data);
        return true;
    }

    /**
     * 格式化挂机参数
     * @param $params
     * @return mixed
     */
    public static function formatEndParams($params)
    {
        $data = [];
        $data['unique_id'] = $params['CallSheetID'];
        $data['call_type'] = $params['call_type'];
        $data['seat_type'] = $params['seat_type'];
        $data['seat_id'] = isset($params['Agent']) ? (int)$params['Agent'] : 0;
        //小号外呼用RealCalled  坐席外呼用CalledNo
        // $data['customer_number'] = isset($params['CalledNo']) ? $params['CalledNo'] : '';
        $data['customer_number'] = isset($params['RealCalled']) ? $params['RealCalled'] : '';
        $data['create_time'] = time();
        $data['ring_time'] = isset($params['RingingDate']) ? strtotime($params['RingingDate']) : 0;
        $data['connect_time'] = empty($params['Begin']) ? 0 : strtotime($params['Begin']);
        $data['call_status'] = self::getCallOutStatus($params['State']);
        $data['finish_time'] = empty($params['End']) ? 0 : strtotime($params['End']);
        $data['record_file'] = isset($params['MonitorFilename']) ? $params['MonitorFilename'] : "";
        $data['show_code'] = isset($params['CallNo']) ? $params['CallNo'] : "";
        $data['user_unique_id'] = isset($params['DialoutStrVar'])?$params['DialoutStrVar'] : "";

        //获取用户(CC)id
        if (!empty($data['seat_id'])) {
            $data['employee_id'] = self::getUserId($data['seat_id']);
        }
        //获取客户id
        if (!empty($data['customer_number'])) {
            $data['student_id'] = StudentService::getStudentIdByMobile($data['customer_number']);
        }
        //双方接通才计算 通话时间
        if (!empty($data['connect_time'])) {
            $data['talk_time'] = $data['finish_time'] - $data['connect_time'];
        }

        self::setEndParams($data);
        return true;
    }

    /**
     * 获取外呼接听状态
     * @param $state
     * @return int
     */
    public static function getCallOutStatus($state)
    {
        switch ($state){
            case self::CALLOUT_STATE_DEALING:
                return CallCenterLogService::CALLOUT_CONN_SUCCESS;
            case self::CALLOUT_STATE_NOT_DEAL:
                return CallCenterLogService::CALLOUT_CONN_FAIL;
            default:
                return CallCenterLogService::CALLOUT_CONN_OTHER_FAIL;
        }
    }

    /**
     * 获取user_field
     * @param $uniqueField
     * @return string
     */
    public static function getUserField($uniqueField)
    {
        return $uniqueField['data']['DialoutStrVar'] ?? '';
    }
}