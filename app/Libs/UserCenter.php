<?php
/**
 * @copyright Copyright (C) 2018 Xiaoyezi.com
 * Created by PhpStorm.
 * User: duhaiqiang
 * Date: 2018/10/31
 * Time: 下午8:02
 */

namespace App\Libs;


use App\Models\StudentModel;
use App\Models\TeacherModel;
use App\Services\DictService;
use GuzzleHttp\Client;
use Slim\Http\StatusCode;

class UserCenter
{
    const RSP_CODE_SUCCESS = 0;
    const ERR_CODE_CONFLICT_USER = 20005;
    const ERR_INVALID_PARAMS = 400;
    const ERR_PASSWORD_STRENGTH = 10011;
    const ERR_USER_NOT_EXIST = 10002;
    const ERR_PASSWORD_ERR = 10007;

    const API_TOKEN_VERIFY = '/rapi/v1/auth/tokencheck';
    const API_AUTHORIZATION = '/rapi/v1/authorization';
    const API_BATCH_AUTHORIZATION = '/rapi/v1/batch_authorization';
    const API_AUTH_UNAUTH = '/rapi/v1/authorization/';
    const API_UPDATEUSER = '/rapi/v1/user/';
    const API_CHANGEPASSWORD = '/rapi/v1/changepassword/';

    const AUTH_APP_ID_STUDENT = 1;
    const AUTH_APP_ID_TEACHER = 2;
    const AUTH_APP_ID_ERP = 4;
    const AUTH_APP_ID_LIEBAO = 5;
    const AUTH_APP_ID_AIPEILIAN = 8;



    private $hostBaseUrl, $appId, $appSecret;

    public function __construct($appId = "", $appSecret = "")
    {
        list($hostBaseUrl, $ucAppId, $ucAppSecret) = DictService::getKeyValuesByArray(Constants::DICT_TYPE_SYSTEM_ENV,
            [Constants::DICT_KEY_UC_HOST_URL, Constants::DICT_KEY_UC_APP_ID, Constants::DICT_KEY_UC_APP_SECRET]);
        $this->hostBaseUrl = $hostBaseUrl . '/api';
        if (empty($appId) || empty($appSecret)){
            $this->appId = $ucAppId;
            $this->appSecret = $ucAppSecret;
        }
    }
    private function commonAPI($api,  $data = [], $method = 'POST')
    {
        try {
            $client = new Client([
                'debug' => false
            ]);
            $fullUrl = $this->hostBaseUrl . $api . '?appId=' . $this->appId . '&secret=' . $this->appSecret;
            SimpleLogger::info(__FILE__ . ':' . __LINE__, ["api" => $fullUrl, "data" => $data]);
            $response = $client->request($method, $fullUrl, $data);
            $body = $response->getBody()->getContents();
            $status = $response->getStatusCode();
            SimpleLogger::info(__FILE__ . ':' . __LINE__, ["api" => $api, "body" => $body, "status" => $status]);

            if (StatusCode::HTTP_OK == $status) {
                $res = json_decode($body, true);
                if (!empty($res['code']) && $res['code'] !== self::RSP_CODE_SUCCESS) {
                    SimpleLogger::info(__FILE__ . ':' . __LINE__, [print_r($res, true)]);
                    return $res;
                }
                return $res;
            } else {
                SimpleLogger::info(__FILE__ . ':' . __LINE__, [print_r($body, true)]);
                $res = json_decode($body, true);
                return $res;
            }

        } catch (\Exception $e) {
            SimpleLogger::error(__FILE__ . ':' . __LINE__, [print_r($e->getMessage(), true)]);
        }
        return false;
    }

    public function CheckToken($token){

        $result = $this->commonAPI(self::API_TOKEN_VERIFY, [
             'headers' => [
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'token' => $token
            ]
        ]);

        if (!empty($result)){
            return $result['data'];
        }
        return [];
    }

    /**
     * 创建学员并授权登录App
     * @param int $authAppID 授权的App
     * @param $mobile
     * @param $name
     * @param string $uuid
     * @param string $birthday
     * @param string $gender
     * @param bool $auth 是否同时授权登录App
     * @return array
     */
    function studentAuthorization($authAppID, $mobile, $name, $uuid="", $birthday = "", $gender ="", $auth = true ){
        if (empty($authAppID)) {
            $authAppID = self::AUTH_APP_ID_STUDENT;
        }

        $userInfo = [];
        if (!empty($mobile)){
            $userInfo['mobile'] = $mobile;
        }
        if (!empty($name)){
            $userInfo['name'] = $name;
        }
        if (!empty($uuid)){
            $userInfo['uuid'] = $uuid;
        }
        if (!empty($birthday)){
            $userInfo['birthday'] = $birthday;
        }
        if (!empty($gender)){
            $userInfo['gender'] = $gender > 2 ? 0 : $gender;
        }
        $result = $this->commonAPI(self::API_AUTHORIZATION, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'auth' => $auth,
                'auth_app_id' => (int)$authAppID,
                'user_info' => $userInfo
            ]
        ]);

        if (!$result){
            return Valid::addErrors([], 'uc_conflict_user', 'uc_conflict_user');
        }
        return $result['data'];
    }

    /**
     * 更新用户信息
     * @param $uuid
     * @param $name
     * @param $birthday
     * @param $gender
     * @return array
     */
    function modifyStudent($uuid, $name, $birthday, $gender){
        $api = self::API_UPDATEUSER . $uuid;
        $userInfo = [];
        $userInfo['name'] = empty($name) ? "" : $name;
        $userInfo['birthday'] = empty($birthday) ? "" : $birthday;
        $userInfo['gender'] = empty($gender) ? StudentModel::GENDER_UNKNOWN : $gender;
        $result = $this->commonAPI($api, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'json' => $userInfo
        ]);
        if (!$result){
            return Valid::addErrors([], 'uc_update_error', 'uc_update_failed');
        }

        return $result;
    }

    /**
     * 创建老师并授权登录App
     * @param $mobile
     * @param $name
     * @param string $uuid
     * @param string $birthday
     * @param string $gender
     * @param string $avatar
     * @param bool $auth 是否同时授权登录App
     * @return array
     */
    function teacherAuthorization($mobile="", $name="", $uuid="", $birthday = "", $gender ="",$avatar="",$auth = true){
        if (empty($mobile) && empty($uuid)){
            return Valid::addErrors([], 'mobile', 'params_is_required');
        }
        $userInfo = [];
        if (!empty($mobile)){
            $userInfo['mobile'] = $mobile;
        }
        if (!empty($name)){
            $userInfo['name'] = $name;
        }
        if (!empty($uuid)){
            $userInfo['uuid'] = $uuid;
        }
        if (!empty($birthday)){
            $userInfo['birthday'] = $birthday;
        }
        if (!empty($gender)){
            $userInfo['gender'] = $gender;
        }
        if (!empty($avatar)){
            $userInfo['avatar'] = $avatar;
        }
        $result = $this->commonAPI(self::API_AUTHORIZATION, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'auth' => $auth,
                'auth_app_id' => self::AUTH_APP_ID_TEACHER,
                'user_info' => $userInfo
            ]
        ]);

        if (!$result){
            return Valid::addErrors([], 'uc_conflict_user', 'uc_conflict_user');
        }
        return $result['data'];
    }

    /**
     * @param $mobile
     * @param $name
     * @param string $uuid
     * @param string $birthday
     * @param string $gender
     * @param string $avatar
     * @return array
     */
    function modifyTeacher($uuid,$mobile, $name, $birthday = "", $gender ="",$avatar=""){
        $api = self::API_UPDATEUSER . $uuid;
        $userInfo = [];
        $userInfo['mobile'] = empty($mobile) ? "" : $mobile;
        $userInfo['name'] = empty($name) ? "" : $name;
        $userInfo['birthday'] = empty($birthday) ? "" : $birthday;
        $userInfo['gender'] = empty($gender) ? TeacherModel::GENDER_UNKNOWN : $gender;
        $userInfo['avatar'] = empty($avatar) ? "" : $avatar;
        $result = $this->commonAPI($api, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'json' => $userInfo
        ]);
        if (!$result){
            return Valid::addErrors([], 'uc_update_error', 'uc_update_failed');
        }

        return $result['data'];
    }

    /**
     * 取消老师APP登录授权
     * @param $uuid
     * @return array
     */
    function teacherUnauthorization($uuid){
        // TODO: 调用位置未确定
        $api = self::API_AUTH_UNAUTH . $uuid;
        $data = [
            'auth' => false,
            'auth_app_id' => self::AUTH_APP_ID_TEACHER
        ];
        $result = $this->commonAPI($api, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'json' => $data
        ]);

        if (!$result){
            return Valid::addErrors([], 'uc_unauth_error', 'uc_unauth_error');
        }

        return $result['data'];

    }

    /**
     * 员工授权
     * @param $loginName
     * @param $pwd
     * @param $name
     * @param $mobile
     * @param bool $auth
     * @param string $uuid
     * @return array
     */
    function employeeAuthorization($loginName, $pwd, $name, $mobile, $auth = true, $uuid="",$encode = false){
        $userInfo = [];
        if (!empty($loginName)){
            $userInfo['login_name'] = $loginName;
        }
        if (!empty($pwd)){
            $userInfo['pwd'] = $pwd;
        }
        if (!empty($mobile)){
            $userInfo['mobile'] = $mobile;
        }
        if (!empty($name)){
            $userInfo['name'] = $name;
        }
        if (!empty($uuid)){
            $userInfo['uuid'] = $uuid;
        }
        $result = $this->commonAPI(self::API_AUTHORIZATION, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'auth' => $auth,
                'auth_app_id' => self::AUTH_APP_ID_ERP,
                'user_info' => $userInfo,
                'password_encoded' => $encode,
            ]
        ]);

        if (!$result){
            return Valid::addErrors([], 'uc_conflict_user', 'uc_conflict_user');
        }
        return $result['data'];
    }

    /**
     * 撤销员工ERP登录授权
     * @param $uuid
     * @return array
     */
    function employeeDisAuth($uuid){
        // TODO: 调用位置未确定
        $api = self::API_AUTH_UNAUTH . $uuid;
        $data = [
            'auth' => false,
            'auth_app_id' => self::AUTH_APP_ID_ERP
        ];
        $result = $this->commonAPI($api, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'json' => $data
        ]);

        if (!$result){
            return Valid::addErrors([], 'uc_unauth_error', 'uc_unauth_error');
        }

        return $result['data'];

    }

    /**
     * 猎豹系统授权（取消授权）
     * @param      $uuid
     * @param bool $auth
     * @return array
     */
    function liebaoAuthorization($uuid, $auth = true){
        $api = self::API_AUTH_UNAUTH . $uuid;
        $data = [
            'auth' => $auth,
            'auth_app_id' => self::AUTH_APP_ID_LIEBAO
        ];
        $result = $this->commonAPI($api, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'json' => $data
        ]);

        if (!$result){
            return Valid::addErrors([], 'uc_auth_error', 'uc_auth_error');
        }

        return $result['data'];

    }


    /**
     * 更改密码
     * @param $uuid
     * @param $newPassword
     * @param $oldPassword
     * @return array
     */
    public function changePassword($uuid, $newPassword, $oldPassword)
    {
        $api = self::API_CHANGEPASSWORD . $uuid;
        $data['new_pwd'] = $newPassword;
        if (!empty($oldPassword)){
            $data['old_pwd'] = $oldPassword;
        }
        $result = $this->commonAPI($api, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'json' => $data
        ]);
        if (!$result || !empty($result['code']) && $result['code'] != 0){
            if ($result['code'] == self::ERR_USER_NOT_EXIST){
                return Valid::addErrors([], 'pwd', 'uc_user_not_exist');
            }else if ($result['code'] == self::ERR_PASSWORD_STRENGTH){
                return Valid::addErrors([],'pwd', 'uc_password_strength');
            }else if ($result['code'] == self::ERR_PASSWORD_ERR){
                return Valid::addErrors([],'old_pwd', 'uc_old_password_error');
            }else{
                return Valid::addErrors([], 'pwd', 'uc_system_error');
            }
        }
        return ['code' => self::RSP_CODE_SUCCESS, 'data' => []];
    }

    /**
     * 批量授权（老师或学生都可以调用此接口）
     * @param $userInfo
     * @param $authAppId
     * @param bool $auth
     * @return array
     */
    public function batchAuthorization($userInfo,$authAppId,$auth = true) {
        if (! is_array($userInfo[0])) {
            return Valid::addErrors([], 'user_info', 'params_is_required');
        }
        //检查参数
        $attrs = ['mobile', 'name', 'birthday', 'gender', 'avatar', 'email', 'pwd', 'uuid'];
        foreach ($userInfo as $user) {
            if (! isset($user['mobile']) || empty($user['mobile'])) {
                return Valid::addErrors([], 'mobile', 'user_mobile_is_required');
            }
            foreach ($attrs as $attr) {
                if (isset($user[$attr]) && !is_string($user[$attr])) {
                    return Valid::addErrors([], $attr, 'param_must_be_string');
                }
            }
        }

        $result = $this->commonAPI(self::API_BATCH_AUTHORIZATION, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'json'    => [
                'auth'        => $auth,
                'auth_app_id' => $authAppId,
                'user_info'   => $userInfo
            ]
        ]);

        return $result;
    }
}