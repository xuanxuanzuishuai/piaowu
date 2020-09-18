<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2018/8/20
 * Time: 下午3:38
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\DictModel;
use App\Models\TRRTSettingModel;
use App\Models\UserSeatModel;
use GuzzleHttp\Client;

class CallCenterTRRTService
{
    const CALL_API_HOST = "http://api.clink.cn";
    const TEL_TYPE_PHONE = 1;        //普通电话
    const TEL_TYPE_EXTENSION = 2;    //分机
    const TEL_TYPE_REMOTE_PHONE = 4; //远程座席

    const SEAT_STATUS_IDLE = 1;     //初始状态 空闲
    const SEAT_STATUS_BUSY = 2;

    const CLINET_TYPE_TEL = 1;     //    1表示电话坐席,2表示电脑座席；默认电话坐席
    const CLIENT_TYPE_COMPUTER = 2;

    //座席上线 msg
    const SEAT_ONLINE_ERR_LOGINED = 12; //座席已登陆
    const SEAT_ONLINE_ERR_OTHER = 99;   //其他错误 联系管理员

    private static $config;
    private static $clidLeftNumbers;

    private static function getConfig()
    {
        return self::$config;
    }

    private static function setConfig($seat_type, $needSeed = true)
    {

        self::$config = array();

        $config = TRRTSettingModel::getSetting($seat_type);

        if ($needSeed == true) {
            $seed = rand(1000, 9999);
            self::$config['seed'] = $seed;
            self::$config['pwd'] = md5($config['password'] . $seed);
        } else {
            self::$config['password'] = $config['password'];
        }

        self::$config['enterpriseId'] = $config['enterprise_id'];
        self::$config['userName'] = $config['username'];

        self::$clidLeftNumbers = explode(",", $config['numbers']);

    }

    private static function getClidLeftNumber()
    {
        $rand = rand(0, count(self::$clidLeftNumbers) - 1);
        return self::$clidLeftNumbers[$rand];
    }

    public static function request($uri, $params, $method = 'POST')
    {
        $client = new Client();
        $params = array_merge($params, self::getConfig());
        SimpleLogger::info(__FILE__ . ':' . __LINE__, ["TRRT url " => self::CALL_API_HOST . $uri, "params" => $params]);
        $response = $client->request($method, self::CALL_API_HOST . $uri, [
            'form_params' => $params,
            'debug' => false
        ]);
        $body = $response->getBody()->getContents();
        SimpleLogger::info(__FILE__ . ':' . __LINE__, ['TRRT body' => $body]);
        if (200 == $response->getStatusCode()) {
            return $data = json_decode($body, true);
        } else {
            SimpleLogger::error(__FILE__ . ':' . __LINE__, ['body' => print_r($body, true)]);

            return ['result' => 'error', 'code' => -1, 'msg' => '网络错误'];
        }
    }

    /**
     * 坐席外呼
     * @param $cno
     * @param $customTel
     * @param $callUserType
     * @param null $clidLeftNumber
     * @internal param $seatId
     * @return mixed
     */
    public static function outCall($cno, $customTel,$callUserType , $clidLeftNumber = null)
    {
        $uri = '/interface/PreviewOutcall';
        $clidLeftNumber = empty($clidLeftNumber) ? self::getClidLeftNumber() : $clidLeftNumber;

        $userField = CallCenterService::setUserField($cno, $customTel ,$callUserType);

        $res = self::request($uri, ['customerNumber' => $customTel, 'cno' => $cno, /*'clidLeftNumber' => $clidLeftNumber,*/ 'userField' =>$userField]);
        if (isset($res['res']) && $res['res'] == 0) {
            $res['userField'] = $userField;
            return $res;
        }else if(isset($res['res']) && $res['res'] != 0){
            $errMsg = DictModel::getKeyValue(Constants::DICT_TR_ERROR, $res['res']);
            $res['errMsg'] = $errMsg;
            return $res;
        }
        return false;
    }

    /**
     * 查询座席绑定电话
     * @param $cno
     * @return mixed
     */
    public static function getSeaterTels($seatType, $cno)
    {
        $uri = "/interfaceAction/clientInterface!queryBindTel.action";
        self::setConfig($seatType);
        // {"result":0,"msg":"操作成功！","data":[{"id":800064,"enterpriseId":3005441,"cno":"2088","tel":"2088","telType":3,"isValidity":"0","isBind":1,"createTime":"2018-06-27 13:58:38"}]}
        $res = self::request($uri, ['cno' => $cno]);

        if (isset($res['result']) && $res['result'] == 0) {
            SimpleLogger::info(__FILE__ . ':' . __LINE__, ['get seat tels' => $res['data']]);
            return $res['data'];
        }
        return null;
    }

    /**
     * 坐席上线
     * @param $cno
     * @param $tel
     * @param int $status
     * @param int $type
     * @return bool
     */
    public static function onlineSeater($seat_type, $cno, $tel, $status = 1, $type = 4)
    {
        $uri = "/interface/client/ClientOnline";
        self::setConfig($seat_type);
        $res = self::request($uri, ['cno' => $cno, 'bindTel' => $tel, 'status' => $status, 'type' => $type]);

        // 12 座席已经登录状态为正常状态 99 先设为正常
        if (isset($res['code']) && ($res['code'] == 0 || $res['code'] == self::SEAT_ONLINE_ERR_LOGINED || $res['code'] == self::SEAT_ONLINE_ERR_OTHER)) {
            return true;
        }

        return $res;
    }

    /**
     * 坐席离线
     * => Not use
     * @param $cno
     * @return bool
     */
    public static function offlineSeater($cno)
    {
        $uri = '/interface/client/ClientOffline';
        $res = self::request($uri, ['cno' => $cno]);
        if (isset($res['code']) && $res['code'] == 0) {
            return true;
        }
        return false;
    }

    /**
     * 新增座席
     * => Not use
     * @param $seat_type
     * @param $cno
     * @param $name
     * @param $pwd
     * @param string $areaCode
     * @param int $clientType
     * @return array|bool|mixed
     */
    public static function addSeater($seat_type, $cno, $name, $pwd, $areaCode = '010', $clientType = self::CLIENT_TYPE_COMPUTER)
    {
        $uri = '/interfaceAction/clientInterface!save.action';
        self::setConfig($seat_type, false);

        // clientType 默认必须为2（电脑座席）
        // skill 使用默认第一个队列
        $skills = self::getSkillList($seat_type);
        $skill = $skills['msg']['data'][0]['id'];
        SimpleLogger::info(__FILE__ . ':' . __LINE__, ['add seat id ' => $skill]);
        $res = self::request($uri, ['cno' => $cno, 'name' => $name, 'pwd' => $pwd, 'areaCode' => $areaCode, 'clientType' => 2,
            'skillIds' => $skill, 'skillLevels' => 1, 'obClid' => implode(",", self::getClidLeftNumber()), 'obClidRule' => 0, 'clientSearchPower' => 3]);

        if (isset($res['result']) && $res['result'] == "success") {
            return true;
        }
        return $res;

    }

    /**
     * 座席是否存在
     * @param $seat_type
     * @param $cno
     * @return bool
     */
    public static function isSeaterExists($seat_type, $cno)
    {
        $uri = '/interfaceAction/clientInterface!validateCno.action';
        self::setConfig($seat_type, false);

        $res = self::request($uri, ['cno' => $cno]);

        if (isset($res['result']) && $res['result'] == "true") {
            return true;
        }
        return false;
    }

    /**
     * 删除座席电话
     * => Not use
     * @param $seat_type
     * @param $cno
     * @param $tel
     * @return array|bool|mixed
     */
    public static function delSeaterTels($seat_type, $cno, $tel)
    {
        $uri = '/interfaceAction/clientInterface!unBindCurrentTel.action';
        self::setConfig($seat_type);

        $res = self::request($uri, ['cno' => $cno, 'tel' => $tel]);
        if (isset($res['result']) && $res['result'] == 0) {
            return true;
        }
        return $res;
    }

    /**
     * 删除座席
     * => Not use
     * @param $seat_type
     * @param $id
     * @return bool
     */
    public static function delSeater($seat_type, $id)
    {
        $uri = '/interfaceAction/clientInterface!delete.action';
        self::setConfig($seat_type, false);

        $res = self::request($uri, ['id' => $id]);
        if (isset($res['result']) && $res['result'] == 'success') {
            return true;
        }

        return false;
    }

    /**
     * 绑定电话
     * @param $seat_type
     * @param $cno
     * @param $tel
     * @return array|bool|mixed
     */
    public static function bindSeaterTels($seat_type, $cno, $tel)
    {
        $uri = '/interface/client/ChangeBindTel';
        self::setConfig($seat_type);
        $res = self::request($uri, ['cno' => $cno, 'bindTel' => $tel]);

        if (isset($res['result']) && $res['result'] == "success") {
            return true;
        }
        return $res;
    }

    /**
     * 查询座席信息
     * => Not use
     * @param $seat_type
     * @param $cno
     * @return array|mixed
     */
    public static function getSeaterInfo($seat_type, $cno)
    {
        $uri = '/interfaceAction/clientInterface!view.action';
        self::setConfig($seat_type, false);
        $res = self::request($uri, ['cno' => $cno]);
        if (isset($res['result']) && $res['result'] == "success") {
            SimpleLogger::info(__FILE__ . ':' . __LINE__, ['seat info ' => $res['msg']]);
            return $res['msg'];
        }
        return $res;
    }

    /**
     * 更新座席密码
     * => Not use
     * @param $seat_type
     * @param $cno
     * @param $pwd
     * @return array
     */
    public static function updateSeatPwd($seat_type, $cno, $pwd)
    {
        $uri = '/interfaceAction/clientInterface!save.action';
        self::setConfig($seat_type, false);

        $seatInfo = self::getSeaterInfo($seat_type, $cno);
        if (isset($seatInfo['result']) && $seatInfo['result'] != 'success' || !isset($seatInfo['id'])) {
            return Valid::addErrors([], 'seat_id', 'seat_id_not_set');
        }
        $res = self::request($uri, ['id' => $seatInfo['id'], 'cno' => $cno, 'name' => $seatInfo['name'], 'areaCode' => empty($seatInfo['areaCode']) ? '0527' : $seatInfo['areaCode'], 'pwd' => $pwd]);
        if (isset($res['result']) && $res['result'] == 'success') {
            return ['code' => 0];
        } else {
            SimpleLogger::info(__FILE__ . ':' . __LINE__, ['update seat pwd' => $res]);
            return Valid::addErrors([], 'seat_pwd', 'seat_id_request_error');
        }

    }

    /**
     * 获取所有座席信息
     *
     */
    public static function getAllSeater($seat_type)
    {
        $uri = '/interfaceAction/clientInterface!list.action';
        self::setConfig($seat_type, false);
        $res = self::request($uri, []);
        return $res;
    }

    /**
     * 获取所有技能
     * @param $seat_type
     * @return array|mixed
     */
    public static function getSkillList($seat_type)
    {
        $uri = '/interfaceAction/skillInterface!list.action';
        self::setConfig($seat_type, false);
        $res = self::request($uri, []);
        return $res;
    }

    /**
     * 获取座席登录信息
     * => Not use
     * @param $db
     * @param $userId
     * @return array|bool
     */
    public static function getSeatLoginInfo($db, $userId)
    {

        $userSeat = UserSeatModel::getUserSeat(['user_id' => $userId]);
        $seatTypeError = null;
        $bindTel = '';
        $bindType = '';
        $res = '';
        if (empty($userSeat) || !UserSeatModel::isTRRT($userSeat['seat_type'])) {
            return false;
        } else {
            $seatType = $userSeat['seat_type'];
            $seatId = $userSeat['seat_id'];
            $seatPwd = $userSeat['pwd'];
            $seatSetting = TRRTSettingModel::getSetting($seatType);
            if (empty($seatSetting)) {
                return false;
            } else {
                $enterpriseId = $seatSetting['enterprise_id'];
                $hotline = $seatSetting['hotline'];

                $bindTels = self::getSeaterTels($seatType, $seatId);
                SimpleLogger::info(__FILE__ . ':' . __LINE__, ['bindTels' => $bindTels]);
                if (empty($bindTels)) {
                    $bindTel = $userSeat['seat_tel'];
                    self::onlineSeater($seatType, $seatId, $bindTel);
                    $res = self::bindSeaterTels($seatType, $seatId, $bindTel);
                } else {
                    foreach ($bindTels as $bindTel) {
                        if ($bindTel['telType'] == 6) {
                            // 默认使用远程座席电话
                            $bindType = 4;
                            $bindTel = $bindTel['tel'];
                            break;
                        } else {
                            // 无远程座席的情况，默认使用最后一个
                            $bindType = 1;
                            $bindTel = $bindTel['tel'];
                        }
                    }
                }
            }
        }

        return [$seatType, $seatId, $seatPwd, $enterpriseId, $hotline, $bindTel, $bindType, $res];

    }

    /**
     * 天润外呼
     * @param $seatType
     * @param $fromSeatId
     * @param $toMobile
     * @param $callUserType
     * @return array
     */
    public static function dialoutTianrun($seatType, $fromSeatId, $toMobile, $callUserType)
    {

        // 获取座席信息
        $bindTels = CallCenterTRRTService::getSeaterTels($seatType, $fromSeatId);
        if (empty($bindTels)) {
            return Valid::addErrors([], 'seat_tel', 'seat_tel_not_set');
        }
        foreach ($bindTels as $bindTel) {
            if ($bindTel['telType'] == 6) {
                // 默认使用远程座席电话
                $telType = 4;
                $bindTel = $bindTel['tel'];
                break;
            } else {
                // 无远程座席的情况，默认使用最后一个
                $telType = 1;
                $bindTel = $bindTel['tel'];
            }
        }

        // 座席上线
        $onlineStatus = CallCenterTRRTService::onlineSeater($seatType, $fromSeatId, $bindTel, 1, $telType);

        // 外呼
        if ($onlineStatus) {
           return CallCenterTRRTService::outCall($fromSeatId, $toMobile,$callUserType);
        }
    }

    /**
     * 通过外呼唯一标示查询是用户线索还是机构线索
     * @param $userField
     * @return int
     */
    public static function getCalledUserType($userField)
    {
        $calledUserType = CallCenterService::CALL_USER_TYPE_LEADS;
        if(empty($userField)) return $calledUserType;

        $userFieldArr = explode('_',$userField);
        $userFieldArrLen = count($userFieldArr);
        if($userFieldArrLen && $userFieldArr[$userFieldArrLen -1] == CallCenterService::CALL_USER_TYPE_AGENT){
            $calledUserType = CallCenterService::CALL_USER_TYPE_AGENT;
        }
        return $calledUserType;

    }

}