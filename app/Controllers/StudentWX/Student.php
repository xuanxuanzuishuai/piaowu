<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/23
 * Time: 15:41
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Models\PlayRecordModel;
use App\Models\StudentModel;
use App\Services\CommonServiceForApp;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Libs\MysqlDB;
use App\Models\StudentModelForApp;
use App\Services\StudentServiceForApp;
use App\Services\WeChatService;
use App\Services\StudentService;
use App\Models\UserRefereeModel;
use App\Models\UserWeixinModel;
use App\Libs\UserCenter;

class Student extends ControllerBase
{

    /** 注册并绑定
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function register(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'mobile_is_required'
            ],
            [
                'key' => 'sms_code',
                'type' => 'required',
                'error_code' => 'sms_code_is_required'
            ],
            [
                'key' => 'org_id',
                'type' => 'integer'
            ],
            [
                'key' => 'referee_type',
                'type' => 'integer'
            ],
            [
                'key' => 'referee_id',
                'type' => 'integer'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $old_token = $this->ci["token"];
        if (!empty($old_token)){
            WeChatService::deleteToken($old_token);
        }
        $app_id = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
        $openId = $this->ci["open_id"];
        // 检查验证码
        if (!CommonServiceForApp::checkValidateCode($params["mobile"], $params["sms_code"])) {
            return $response->withJson(Valid::addAppErrors([], 'validate_code_error'), StatusCode::HTTP_OK);
        }

        //验证手机号是否已存在
        $student_info = StudentModelForApp::getStudentInfo("", $params['mobile']);

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        if (empty($student_info["id"])) {
            $student_id = StudentServiceForApp::studentRegister($params["mobile"],
                StudentModel::CHANNEL_WE_CHAT_SCAN, $params["name"]);
            if (empty($student_id)) {
                return $response->withJson(Valid::addAppErrors([], 'register_failed'), StatusCode::HTTP_OK);
            }

            // 转介绍
            if (!empty($params["referee_id"]) and !empty($params["referee_type"])) {
                UserRefereeModel::insertReferee($params["referee_id"], $params["referee_type"], $student_id);
            }

            $student_info = StudentModelForApp::getStudentInfo("", $params['mobile']);
        }

        // 绑定该用户与微信
        if (!empty($openId)) {
            UserWeixinModel::boundUser($openId, $student_info["id"], $app_id, WeChatService::USER_TYPE_STUDENT, 1);
        }

        // 绑定机构
        if (!empty($params["org_id"])) {
            StudentService::bindOrg($params["org_id"], $student_info["id"]);
        }
        $db->commit();

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => []
        ], StatusCode::HTTP_OK);
    }

    /** token失效时获取token
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function login(Request $request, Response $response)
    {

        $rules = [
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $old_token = $this->ci["token"];
        if (!empty($old_token)){
            WeChatService::deleteToken($old_token);
        }

        $app_id = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
        $openId = $this->ci["open_id"];
        if (empty($openId)) {
            return $response->withJson(Valid::addAppErrors([], 'need_bound'), StatusCode::HTTP_OK);
        }
        $bound_info = UserWeixinModel::getBoundInfoByOpenId($openId);
        // 没有找到该openid的绑定关系
        if (empty($bound_info)) {
            return $response->withJson(Valid::addAppErrors([], 'need_bound'), StatusCode::HTTP_OK);
        }

        $token = WeChatService::generateToken($bound_info["user_id"], WeChatService::USER_TYPE_STUDENT,
            $app_id, $openId);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ["token" => $token]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 发送注册验证码
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sendSmsCode(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'mobile_is_required'
            ],
            [
                'key' => 'mobile',
                'type' => 'regex',
                'value' => '/^[0-9]{11}$/',
                'error_code' => 'mobile_format_error'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $errorCode = CommonServiceForApp::sendValidateCode($params['mobile'],
            CommonServiceForApp::SIGN_WX_STUDENT_APP);
        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => []
        ], StatusCode::HTTP_OK);
    }

    /**
     * 我的账户
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function accountDetail(Request $request, Response $response)
    {
        $user_id = $this->ci['user_info']['user_id'];
        $student_info = StudentModelForApp::getStudentInfo($user_id, null);
        if (empty($student_info)){
            return $response->withJson(Valid::addAppErrors([], 'need_bound'), StatusCode::HTTP_OK);
        }
        $lesson_num = PlayRecordModel::getDistinctLessonIdCount($user_id);
        $duration = PlayRecordModel::getSumPlayRecordDuration($user_id);
        $expire_date = $student_info["sub_end_date"];
        if (empty($expire_date)){
            $expire_date = "";
        } else {
            $expire_date = substr($expire_date, 0, 4) . "-" .
                substr($expire_date, 4, 2) . "-" . substr($expire_date, 6, 2);
        }
        $account_info = [
            "mobile" => substr($student_info["mobile"], 0, 3) . "****" .
                substr($student_info["mobile"], 7, 4),
            "name" => $student_info["name"],
            "thumb" => $student_info["thumb"],
            "lesson_num" => $lesson_num,
            "duration" => $duration,
            "expired_date" => $expire_date,
            "sub_status" => (int)$student_info["name"],
        ];

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $account_info
        ], StatusCode::HTTP_OK);
    }

    /**
     * 编辑账户信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function editAccountInfo(Request $request, Response $response){
//        $rules = [
//            [
//                'key' => 'name',
//                'type' => 'required',
//                'error_code' => 'name_is_required'
//            ],
//            [
//                'key' => 'thumb',
//                'type' => 'required',
//                'error_code' => 'thumb_is_required'
//            ]
//        ];
//
        $params = $request->getParams();
//        $result = Valid::appValidate($params, $rules);
//        if ($result['code'] != Valid::CODE_SUCCESS) {
//            return $response->withJson($result, StatusCode::HTTP_OK);
//        }

        $update_info = [];
        if (!empty($params["thumb"])){
            $update_info["thumb"] = $params["thumb"];
        }
        if (!empty($params["name"])){
            $update_info["name"] = $params["name"];
        }

        if (!empty($update_info)){
            $user_id = $this->ci['user_info']['user_id'];
            $db = MysqlDB::getDB();
            $db->beginTransaction();
            StudentModelForApp::updateRecord($user_id, $update_info);
            $db->commit();
        }
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => []
        ], StatusCode::HTTP_OK);

    }
}
