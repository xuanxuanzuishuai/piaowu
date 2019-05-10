<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/23
 * Time: 15:41
 */

namespace App\Controllers\StudentOrgWX;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
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

class StudentOrg extends ControllerBase
{
    public const ORG_ID = 1;

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

        // todo 现在绑定机构写死为1了
        StudentService::bindOrg(self::ORG_ID, $student_info["id"]);

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

        $token = WeChatService::generateToken($bound_info["user_id"], WeChatService::USER_TYPE_STUDENT_ORG,
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

}
