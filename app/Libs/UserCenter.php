<?php
/**
 * @copyright Copyright (C) 2018 Xiaoyezi.com
 * Created by PhpStorm.
 * User: duhaiqiang
 * Date: 2018/10/31
 * Time: 下午8:02
 */

namespace App\Libs;
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
    const API_AUTHORIZATION_BY_UUID = '/rapi/v1/authorization/%s';
    const API_BATCH_AUTHORIZATION = '/rapi/v1/batch_authorization';
    const API_AUTH_UNAUTH = '/rapi/v1/authorization/';
    const API_UPDATEUSER = '/rapi/v1/user/';
    const API_CHANGEPASSWORD = '/rapi/v1/changepassword/';

    const AUTH_APP_ID_STUDENT = 11;
    const AUTH_APP_ID_TEACHER = 12;
    const AUTH_APP_ID_ERP = 4;
    const AUTH_APP_ID_LIEBAO = 5;
    const AUTH_APP_ID_AIPEILIAN_STUDENT = 8; //AI陪练学生
    const AUTH_APP_ID_AIPEILIAN_TEACHER = 13; //AI陪练老师
    const AUTH_APP_ID_DSS = 10; //机构员工
    const APP_ID_PRACTICE = 1; //真人陪练
    //TheONE国际钢琴课公众号，与"AI陪练老师"共用一个APP_ID，以下APP_ID仅为了区分不同的app_id和secret，不能用作其他用途
    const AUTH_APP_ID_AIPEILIAN_CLASSROOM_TEACHER = 0;

    private $hostBaseUrl, $appId, $appSecret;

    public function __construct($appId, $appSecret)
    {
        $hostBaseUrl = DictConstants::get(DictConstants::USER_CENTER, "host");
        $this->hostBaseUrl = $hostBaseUrl . '/api';
        $this->appId = $appId;
        $this->appSecret = $appSecret;
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
                'auth_app_id' => self::AUTH_APP_ID_DSS,
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
}