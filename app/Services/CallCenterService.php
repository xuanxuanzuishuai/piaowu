<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2018/8/20
 * Time: 下午3:36
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Models\DictModel;
use App\Models\EmployeeSeatModel;
use App\Models\UserSeatModel;

class CallCenterService
{

    /**
     * 呼叫系统类型：1： 容联七陌  2：天润
     */
    const SEAT_SYS_RONGLIAN = '1';
    const SEAT_SYS_TIANRUN = '2';
    const CALL_USER_TYPE_LEADS = 1; //被外呼用户类型
    const CALL_USER_TYPE_AGENT = 2;

    public function dialout($seatType, $fromSeatId, $toMobile, $extendType = '', $callUserType = self::CALL_USER_TYPE_LEADS){
        if ($seatType == EmployeeSeatModel::SEAT_RONGLIAN) {
            return CallCenterRLService::dialoutRonglian($fromSeatId, $toMobile, $extendType);
        } else if (EmployeeSeatModel::isTRRT($seatType)) {
            return CallCenterTRRTService::dialoutTianrun($seatType, $fromSeatId, $toMobile, $callUserType);
        }
        SimpleLogger::info(__FILE__ . ':' . __LINE__, ['params' => $seatType]);
        return null;
    }

    /**
     * 设置外呼用户唯一标示 用来呼叫数据回调时与跟进记录关联
     * @param $cno
     * @param $customTel
     * @param int $callUserType
     * @return string
     */
    public static function setUserField($cno, $customTel ,$callUserType = CallCenterService::CALL_USER_TYPE_LEADS)
    {
        $appPrefix = self::getAppPrefix();
        if($callUserType != CallCenterService::CALL_USER_TYPE_LEADS){
            $userField = $appPrefix . "_" . $cno . "_" . $customTel . "_" . microtime(true) ."_". $callUserType;
        }else{
            $userField = $appPrefix . "_" . $cno . "_" . $customTel . "_" . microtime(true);
        }
        return $userField;

    }

    /**
     * 获取应用前缀
     * @return string
     */
    public static function getAppPrefix()
    {
        return DictModel::getKeyValue(Constants::DICT_TYPE_SYSTEM_ENV, Constants::DICT_KEY_CALL_CENTER_APP_PREFIX);
    }


    public static function judgeCallBack()
    {

    }
}