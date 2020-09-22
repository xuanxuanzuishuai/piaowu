<?php
/**
 * Created by PhpStorm.
 * User: liliang
 * Date: 18/7/10
 * Time: 上午10:56
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Models\CallCenterLogModel;
use App\Models\EmployeeSeatModel;
use Medoo\Medoo;


class CallCenterLogService
{

    public static $ring_params = array(); //响铃参数
    public static $end_params = array();  //挂机参数

    const CALLOUT_CONN_SUCCESS = 1;     //外呼成功 双方接听
    const CALLOUT_CONN_TIMEOUT = 11;    //外呼超时 座席接听，客户未接听(超时)
    const CALLOUT_CONN_EMPTY_CODE = 12; //外呼空号 座席接听，客户未接听(空号拥塞)
    const CALLOUT_CONN_FAIL = 13;       //外呼座席未接听
    const CALLOUT_CONN_OTHER_FAIL = 14;      //其他错误

    const CALLIN_CONN_SUCCESS = 2;      //来电接通 坐席接听
    const CALLIN_CONN_SEAT_FAIL = 21;   //已呼叫座席，座席未接听
    const CALLIN_CONN_SYS_SUCCESS = 22; //系统接通
    const CALLIN_CONN_SYS_FAIL = 23;    //系统未接通
    const CALLIN_CONN_OTHER_FAIL = 24;  //其他错误

    const CRM_SEAT_ID_START_WITH = 2;  //crm 坐席以2开头
    const CMS_SEAT_ID_START_WITH = 3;  //cms 坐席以3开头

    const CMS_SITE_TYPE = 1;           //cms 回调标志
    const CRM_SITE_TYPE = 0;           //crm 回调标志

    /**
     * 记录响铃时间
     * @return bool
     */
    public static function logCallRingTime()
    {
        //check if exists
        $params = self::getRingParams();

        $res = CallCenterLogModel::getRecordByUniqueId($params['unique_id']);

        //通话记录已存在，且有ring_time 直接返回。
        if (!empty($res['ring_time'])) {
            return true;
        }
        if (empty($res)) {
            //insert
            $res = CallCenterLogModel::saveCallLog($params);
            return $res ? true : false;
        }
        return true;
    }

    /**
     * 记录挂机时间
     * @return bool
     * @throws
     */
    public static function logCallEndTime()
    {
        $params = self::getEndParams();

        $res = CallCenterLogModel::getRecordByUniqueId($params['unique_id']);
        //通话记录已存在，且有finish_time 直接返回。
        if (!empty($res['finish_time'])) {
            return true;
        }
        if (empty($res)) {
            //insert
            $res = CallCenterLogModel::saveCallLog($params);
        } else {
            //update
            $res = CallCenterLogModel::updateCalllog($params);
        }

        return $res ? true : false;
    }

    /**
     * 查看座席对应cc
     * @param $seat_id
     * @return int
     */
    public static function getEmployeeId($seat_id)
    {
        $employeeId = MysqlDB::getDB()->get(EmployeeSeatModel::$table, 'employee_id', ['seat_id' => $seat_id]);
        $employeeId = !empty($employeeId) ? $employeeId : 0;

        return $employeeId;
    }

    /**
     * 获取响铃参数
     * @return array
     */
    public static function getRingParams()
    {
        return self::$ring_params;
    }

    /**
     * 设置响铃参数
     * @param $params
     */
    public static function setRingParams($params)
    {
        self::$ring_params = $params;

    }

    /**
     * 获取挂机参数
     * @return array
     */
    public static function getEndParams()
    {
        return self::$end_params;
    }

    /**
     * 设置挂机参数
     * @param $params
     */
    public static function setEndParams($params)
    {
        self::$end_params = $params;
    }

    /**
     * 获取用户详细数据
     * @param $params
     * @return array
     * @internal param bool $onlyCount
     */
    public static function getUserCallData($params)
    {
        $where = [];
        $where['AND'][CallCenterLogModel::$table . '.student_id'] = $params['student_id'];
        //只取外呼数据
        $where['AND'][CallCenterLogModel::$table . '.call_type'] = CallCenterLogModel::CALL_TYPE_OUT;

        //获取所有数据 进行分页使用
        $fields = ['total' => Medoo::raw("count(*)")];
        $rowTotal = CallCenterLogModel::getUserRecordCount($fields, $where);
        $rowTotal = $rowTotal['total'];
        $data = [];
        if ($rowTotal > 0) {
            $page = isset($params['page']) ? (int)$params['page'] : 1;
            $count = isset($params['count']) ? (int)$params['count'] : StudentService::DEFAULT_COUNT;
            if ($page > 0 && $count > 0) {
                $where['LIMIT'] = [($page - 1) * $count, $count];
            }

            $data = CallCenterLogModel::getUserRecord($where);
        }
        //格式化输出结果
        if(!empty($data)){
            $data = self::formatData($data);
        }
        return [$rowTotal, $data];
    }

    /**
     * 格式化通话记录
     * @param $data
     * @return array
     */
    public static function formatData($data)
    {
        $callStatusMap = DictService::getTypeMap(Constants::DICT_TYPE_CALL_OUT_STATUS);
        $result = [];
        foreach($data as $val){
            $item = [];
            $item['student_name'] = $val['student_name'];
            $item['student_mobile'] = Util::hideUserMobile($val['student_mobile']);
            $item['show_code'] = $val['show_code'];
            $item['ring_time'] = !empty($val['ring_time']) ? date("Y-m-d H:i:s", $val['ring_time']) : '/';
            $item['connect_time'] = !empty($val['connect_time']) ? date("Y-m-d H:i:s", $val['connect_time']) : '/';
            $item['finish_time'] = !empty($val['finish_time']) ? date("Y-m-d H:i:s", $val['finish_time']) : '/';
            $item['talk_time'] = $val['talk_time'];
            $item['call_status'] = $callStatusMap[$val['call_status']] ?? '';
            $item['record_file'] = self::formatRecordFile($val['seat_type'], $val['cdr_enterprise_id'], $val['record_file']);
            $result[] = $item;
        }
        return $result;
    }

    /**
     * 处理录音文件
     * @param $siteType
     * @param $enterPriseId
     * @param $file
     * @return mixed|string
     */
    public static function formatRecordFile($siteType, $enterPriseId, $file)
    {
        if($siteType == CallCenterLogModel::SEAT_RONGLIAN){
            return $file;
        }

        $formatRecordFile = "";
        return $formatRecordFile;
    }

}