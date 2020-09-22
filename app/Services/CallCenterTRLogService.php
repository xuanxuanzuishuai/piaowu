<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 18/7/11
 * Time: 上午10:06
 */

namespace App\Services;

use App\Libs\Constants;
use App\Models\CallCenterLogModel;
use App\Models\DictModel;
use App\Models\EmployeeModel;


class CallCenterTRLogService extends CallCenterLogService
{
    const CALLIN_CONN_SUCCESS = 1;     //来电双方通话成功
    const CALLIN_CONN_SEAT_FAIL = 2;   //坐席未接听
    const CALLIN_CONN_SYS_SUCCESS = 3; //系统接通
    const CALLIN_CONN_OTHER_FAIL = 4;  //其他错误
    //其他系统未接听状态则返回 默认状态

    const CALLOUT_CONN_SUCCESS = 28;   //外呼双方通话成功
    const CALLOUT_CONN_TIMEOUT = 21;   //外呼超时
    const CALLOUT_CONN_EMPTY_CODE = 22;//外呼空号
    const CALLOUT_CONN_FAIL = 24;      //外呼失败

    const CALLBACK_CALLOUT_RINGING = 'outcall_ringing';
    const CALLBACK_CALLOUT_OK = 'outcall_ok';
    const CALLBACK_CALLOUT_COMPLETE = 'outcall_complete';

    /**
     * 获取天润坐席类型
     * @return array
     */
    public static function getSeatType()
    {
        return  [
            EmployeeModel::SEAT_TIANRUN_MANUAL,
            EmployeeModel::SEAT_TIANRUN_AUTOMATIC,
            EmployeeModel::SEAT_TIANRUN_CUSTOMER_SERVICE
        ];
    }

    /**
     * 根据坐席获取用户id
     * @param $seatId
     * @return int|mixed
     */
    public static function getUserId($seatId)
    {
        $seatType = self::getSeatType();
        return EmployeeModel::getUserId($seatId, $seatType);
    }

    /**
     * 格式化响铃参数
     * @param $params
     * @return bool
     */
    public static function formatRingParams($params)
    {
        //若参数不对直接返回

        if (empty($params) || empty($params['uniqueId'])) {
            return false;
        }

        $data = array();

        $data['unique_id'] = $params['uniqueId'];
        $data['call_type'] = $params['call_type'];
        $data['seat_type'] = $params['seat_type'];

        $data['seat_id'] = isset($params['cno']) ? (int)$params['cno'] : 0;

        if (!empty($data['seat_id'])) {
            $data['user_id'] = self::getUserId($data['seat_id']);
        }

        $data['customer_number'] = isset($params['customerNumber']) ? $params['customerNumber'] : '';
        //check if user exists
        if (!empty($data['customer_number'])) {
            $data['lead_id'] = self::getLeadId($data['customer_number'] ,$params['userField']);
        }

        $data['create_time'] = time();
        $data['ring_time'] = time(); //取当前时间，并不准确
        $data['site_type'] = isset($params['userField']) && !empty($params['userField'])? 0:1; //2018-09-14 临时处理
        self::setRingParams($data);

        return true;
    }

    /**
     * 格式化挂机参数
     * @param $params
     * @return bool
     */
    public static function formatEndParams($params)
    {
        //若参数不对直接返回

        if (empty($params) || empty($params['cdr_main_unique_id'])) {
            return false;
        }

        $data = array();
        $data['unique_id'] = $params['cdr_main_unique_id'];
        $data['call_type'] = $params['call_type'];
        $data['seat_type'] = $params['seat_type'];
        $data['seat_id'] = $params['cdr_bridged_cno'];

        //get cc_id
        if (!empty($data['seat_id'])) {

            $data['user_id'] = self::getUserId($data['seat_id']);
        }

        $data['customer_number'] = isset($params['cdr_customer_number']) ? $params['cdr_customer_number'] : '';
        //check if user exists 新增处理机构的日志
        if (!empty($data['customer_number'])) {
            $data['lead_id'] = self::getLeadId($data['customer_number'] , $params['userField']);
        }

        $data['create_time'] = time();
        $data['ring_time'] = isset($params['cdr_start_time']) ? $params['cdr_start_time'] : 0;

        $data['connect_time'] = self::getConnectTime($params);
        //新增状态参数
        $data['call_status'] = isset($params['cdr_status']) ? $params['cdr_status'] : 0;
        $data['call_status'] = self::getStatus($params['call_type'], $data['call_status']);

        $data['finish_time'] = isset($params['cdr_end_time']) ? $params['cdr_end_time'] : 0;
        $data['talk_time'] = 0;
        //双方接通才计算 通话时间
        if (!empty($data['connect_time'])) {
            $data['talk_time'] = $data['finish_time'] - $data['connect_time'];
        }
        //录音文件名
        $data['record_file'] = isset($params['cdr_record_file']) ? $params['cdr_record_file'] : "";
        //外显号码
        $data['show_code'] = isset($params['cdr_number_trunk']) ? $params['cdr_number_trunk'] : "";
        //为兼容 通时在cms crm 进行外呼 新增此字段site_type 默认 0 crm
        //$data['site_type'] = self::getSiteType($data['seat_id']);
        $data['site_type'] = isset($params['userField']) && !empty($params['userField'])? 0:1; //2018-09-14 临时处理
        //天润 企业id
        $data['cdr_enterprise_id'] = isset($params['cdr_enterprise_id']) ? (int)$params['cdr_enterprise_id'] : 0;
        //自定义唯一id
        $data['user_unique_id'] = isset($params['userField'])?$params['userField'] : "";
        self::setEndParams($data);

        return true;

    }

    /**
     * 处理推送返回值
     * @param $flag
     * @return array
     */
    public static function getMessage($flag)
    {
        $success = ['result' => 'success', 'description' => 'CDR记录接收成功'];
        $failure = ['result' => 'error', 'description' => 'CDR记录接收失败'];
        return $flag ? $success : $failure;

    }

    /**
     * 获取双方接通时间点
     * @param $params
     * @return int
     */
    public static function getConnectTime($params)
    {
        $connect_time = 0;

        /*
         * cdr_status    通话状态    1:座席接听 2:已呼叫座席，座席未接听 3:系统接听
         * 4:系统未接-IVR配置错误 5:系统未接-停机 6:系统未接-欠费 7:系统未接-黑名单
         * 8:系统未接-未注册 9:系统未接-彩铃 10:网上400未接受 11:系统未接-呼叫超出营帐中设置的最大限制
         * 12:其他错误
         * 当状态为 1 时 确认双方成功 才进行计算 通话时长
         *
         * 接通时间取  cdr_bridge_time    桥接时间，留言时用作留言开始时间    UNIX时间戳
         */

        if ($params['call_type'] == CallCenterLogModel::CALL_TYPE_IN) {
            if (isset($params['cdr_status']) && $params['cdr_status'] == self::CALLIN_CONN_SUCCESS) {
                $connect_time = !empty($params['cdr_bridge_time']) ? $params['cdr_bridge_time'] : 0;
            }
        }
        /**
         * cdr_status    通话状态    21:（点击外呼、预览外呼时）座席接听，客户未接听(超时)
         * 22:（点击外呼、预览外呼时）座席接听，客户未接听(空号拥塞)
         * 24:（点击外呼、预览外呼时）座席未接听 28:双方接听
         * 当状态为28 时 确认双方成功 才进行计算 通话时长
         * 取 这个为双方接通时间 cdr_bridge_time    客户接听时间
         */
        if ($params['call_type'] == CallCenterLogModel::CALL_TYPE_OUT) {
            if (isset($params['cdr_status']) && $params['cdr_status'] == self::CALLOUT_CONN_SUCCESS) {
                $connect_time = !empty($params['cdr_bridge_time']) ? $params['cdr_bridge_time'] : 0;
            }
        }

        return $connect_time;

    }

    /**
     * 获取通话状态
     * @param $callType
     * @param $status
     * @return int
     */
    public static function getStatus($callType, $status)
    {
        if ($callType == CallCenterLogModel::CALL_TYPE_IN) {
            return self::formatCallInStatus($status);
        }
        if ($callType == CallCenterLogModel::CALL_TYPE_OUT) {
            return self::formatCallOutStatus($status);
        }

        return 0;
    }

    /**
     * 来电状态标准化
     * @param $status
     * @return int
     */
    public static function formatCallInStatus($status)
    {
        switch ($status) {
            case self::CALLIN_CONN_SUCCESS:
                $formatStatus = parent::CALLIN_CONN_SUCCESS;
                break;
            case self::CALLIN_CONN_SEAT_FAIL:
                $formatStatus = parent::CALLIN_CONN_SEAT_FAIL;
                break;
            case self::CALLIN_CONN_SYS_SUCCESS:
                $formatStatus = parent::CALLIN_CONN_SYS_SUCCESS;
                break;
            case self::CALLIN_CONN_OTHER_FAIL:
                $formatStatus = parent::CALLIN_CONN_OTHER_FAIL;
                break;
            default:
                $formatStatus = parent::CALLIN_CONN_SYS_FAIL;

        }
        return $formatStatus;
    }

    /**
     * 外呼状态标准化
     * @param $status
     * @return int
     */
    public static function formatCallOutStatus($status)
    {
        switch ($status) {
            case self::CALLOUT_CONN_SUCCESS:
                $formatStatus = parent::CALLOUT_CONN_SUCCESS;
                break;
            case self::CALLOUT_CONN_TIMEOUT:
                $formatStatus = parent::CALLOUT_CONN_TIMEOUT;
                break;
            case self::CALLOUT_CONN_EMPTY_CODE:
                $formatStatus = parent::CALLOUT_CONN_EMPTY_CODE;
                break;
            case self::CALLOUT_CONN_FAIL:
                $formatStatus = parent::CALLOUT_CONN_FAIL;
                break;
            default:
                $formatStatus = parent::CALLOUT_CONN_OTHER_FAIL;
        }
        return $formatStatus;
    }

    /**
     * 获取坐席对应的不同用户后台
     * @param $seatId
     * @return int
     */
    public static function getSiteType($seatId)
    {
        $siteType = parent::CRM_SITE_TYPE;

        if (strpos($seatId, (string)parent::CMS_SEAT_ID_START_WITH) === 0) {
            $siteType = parent::CMS_SITE_TYPE;
        }

        return $siteType;

    }

    /**
     * 处理天润录音文件
     * @param $siteInfo
     * @param $file
     * @return mixed|string
     */
    public static function formatTRRecordFile($siteInfo, $file)
    {

        $enterpriseId = $siteInfo['enterprise_id'];
        $userName = $siteInfo['username'];

        $seed = rand(100000, 999999);
        $pwd = md5($siteInfo['password'] . (string)$seed);
        $params = [];
        $params['enterpriseId'] = $enterpriseId;
        $params['userName'] = $userName;
        $params['pwd'] = $pwd;
        $params['seed'] = $seed;

        $query = http_build_query($params);
        //处理日期
        $fileArr = explode('-', $file);
        $fileDir = date("Ymd", strtotime($fileArr[1]));

        $url = DictModel::getKeyValue(Constants::DICT_TYPE_SYSTEM_ENV, 'TIANRUN_RECORDFILE_URL');
        $url = $url . $fileDir . DIRECTORY_SEPARATOR . $file . '?' . $query;

        return $url;


    }

    /**
     * 外呼响铃
     * @param $params
     * @return bool
     */
    public static function outCallRinging($params)
    {
        $params['call_type'] = CallCenterLogModel::CALL_TYPE_OUT;
        $params['seat_type'] = CallCenterLogModel::SEAT_TIANRUN;

        $res = self::formatRingParams($params);
        if ($res == false) {
            return false;
        }

        return self::logCallRingTime();
    }

    /**
     * 外呼挂机
     * @param $params
     * @return bool
     */
    public static function outCallComplete($params)
    {
        $params['call_type'] = CallCenterLogModel::CALL_TYPE_OUT;
        $params['seat_type'] = CallCenterLogModel::SEAT_TIANRUN;

        $res = self::formatEndParams($params);
        if ($res == false) {
            return false;
        }
        return self::logCallEndTime();
    }

    /**
     * 获取unique_id
     * @param $params
     * @return mixed
     */
    public static function getUniqueField($params)
    {
        return $params['data']['cdr_main_unique_id'] ?? '';
    }
}