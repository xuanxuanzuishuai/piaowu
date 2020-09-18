<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2018/8/20
 * Time: 下午2:52
 */

namespace App\Services;


use App\Libs\Valid;
use App\Models\EmployeeSeatModel;

class EmployeeSeatService
{

    /**
     * 绑定用户座席
     * @param $userId
     * @param $seatId
     * @param $seatType
     * @param $extendType
     * @return array
     */
    public static function bindUserSeatService($userId, $seatId, $seatType, $extendType)
    {
        //获取坐席信息
        $userSeat = EmployeeSeatModel::getUserSeat([
            'AND' => [
                'seat_id' => $seatId,
                'seat_type' => $seatType
            ]
        ]);

        //判断坐席号是否占用
        if (!empty($userSeat)) {
            if ($userSeat['user_id'] == $userId) {
                return ['code' => Valid::CODE_SUCCESS, 'data' => []];
            } else {
                return Valid::addErrors([], 'seat_id', 'seat_id_used');
            }
        }

        // 判断座席是否存在
        if (EmployeeSeatModel::isTRRT($seatType)) {
            $res = CallCenterTRRTService::isSeaterExists($seatType, $seatId);
            //不可使用
            if ($res == false) {
                return Valid::addErrors([], 'seat_id', 'seat_id_not_set');
            }
        }

        //判断用户是否已经绑定当前类型坐席
        $seat = self::getUserSeat($userId, $seatType);
        if (empty($seat['seat_id'])) {
            $result = ['code' => Valid::CODE_SUCCESS, 'data' => []];
            //处理 容联
            if ($seatType == EmployeeSeatModel::SEAT_RONGLIAN) {
                $result = self::addRLSeater($userId, $seatId, $seatType, $extendType);
            }
            // 天润
            if (EmployeeSeatModel::isTRRT($seatType)) {
                $result = self::addTRRTSeater($userId, $seatId, $seatType);
            }
            return $result;
        }
        return Valid::addErrors([], 'seat_id', 'seat_id_update_forbidden');
    }

    /**
     * 获取用户指定坐席
     * @param $userId
     * @param null $seatType
     * @return mixed
     */
    public static function getUserSeat($userId, $seatType = null)
    {
        $where['employee_id'] = $userId;
        if(!empty($seatType)){
            $where['seat_type'] = $seatType;
        }
        return EmployeeSeatModel::getRecord($where);
    }

    /**
     * 获取用户坐席
     * @param $userId
     * @param null $seatType
     * @return mixed
     */
    public static function getUserSeats($userId, $seatType = null)
    {
        $where['employee_id'] = $userId;
        if(!empty($seatType)){
            $where['seat_type'] = $seatType;
        }
        return EmployeeSeatModel::getRecords($where);
    }

    /**
     * 设置在用坐席
     * @param $userId
     * @param $seatType
     * @return mixed
     */
    public static function setOnUseSeatService($userId, $seatType)
    {
        $seat = self::getUserSeat($userId, $seatType);
        if(empty($seat)){
            return Valid::addErrors([], 'seat_id', 'seat_type_not_set');
        }
        self::blockUserSeat($userId);
        self::setOnUseSeat($userId, $seatType);
        return ['code' => Valid::CODE_SUCCESS, 'data' => []];
    }

    /**
     * 设置坐席为在用
     * @param $userId
     * @param $seatType
     * @return mixed
     */
    public static function setOnUseSeat($userId, $seatType)
    {
        $userSeat = self::getUserSeat($userId, $seatType);
        return EmployeeSeatModel::updateRecord($userSeat['id'], ['status' => EmployeeSeatModel::ON_USE]);
    }

    /**
     * 设置用户所有坐席为'停用'状态
     * @param $userId
     * @return int|null
     */
    public static function blockUserSeat($userId)
    {
        $userSeats = self::getUserSeats($userId);
        if(empty($userSeats)){
            return true;
        }
        foreach($userSeats as $seat){
             EmployeeSeatModel::updateRecord($seat['id'], ['status' => EmployeeSeatModel::NOT_USE]);
        }
        return true;
    }

    /**
     * 解绑用户座席
     * @param $userId
     * @param $seatType
     */
    public static function unbindUserSeatService($userId, $seatType)
    {
        $userSeat = self::getUserSeat($userId, $seatType);
        if (!empty($userSeat)) {
            EmployeeSeatModel::delById($userSeat['id']);
        }
    }

    /**
     * 新增天润座席
     * @param $userId
     * @param $seatId
     * @param $seatType
     * @return array
     */
    public static function addTRRTSeater($userId, $seatId, $seatType)
    {
        //查询电话
        $bindTels = CallCenterTRRTService::getSeaterTels($seatType, $seatId);

        $bindTel = '';
        if (isset($bindTels) && !empty($bindTels)) {
            foreach ($bindTels as $bindTel) {
                if ($bindTel['telType'] == 6) {
                    // 默认使用远程座席电话
                    $bindTel = $bindTel['tel'];
                    break;
                } else {
                    // 无远程座席的情况，默认使用最后一个
                    $bindTel = $bindTel['tel'];
                }
            }
        }

//        // 更新座席密码用于登录
//        $res = CallCenterTRRTService::updateSeatPwd($seatType, $seatId, uniqid());
//        if ($res['code'] == 1) {
//            return $res;
//        }
        EmployeeSeatModel::addUserSeat($userId, $seatId, $seatType, $bindTel);
        return ['code' => Valid::CODE_SUCCESS, 'data' => []];
    }

    /**
     * 新增容联座席
     * @param $userId
     * @param $seatId
     * @param $seatType
     * @param $extendType
     * @return mixed
     */
    public static function addRLSeater($userId, $seatId, $seatType, $extendType)
    {
        if(empty($extendType)){
            return Valid::addErrors([], 'seat_id', 'seat_extend_type_is_required');;
        }
        EmployeeSeatModel::addUserSeat($userId, $seatId, $seatType, null, $extendType);
        return ['code' => Valid::CODE_SUCCESS, 'data' => []];
    }

    /**
     * 获取用户座席信息
     * @param $id
     * @return mixed
     */
    public static function getEmployeeSeatInfo($id)
    {
        $where = ['employee_id' => $id];
        $employeeSeat = EmployeeSeatModel::getEmployeeSeat($where);
        return $employeeSeat;
    }
}