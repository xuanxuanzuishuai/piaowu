<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/21
 * Time: 11:07
 */

namespace App\Controllers\TeacherWX;

use App\Controllers\ControllerBase;
use App\Libs\Util;
use App\Libs\Valid;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Libs\MysqlDB;
use App\Models\TeacherModelForApp;
use App\Services\TeacherServiceForApp;
use App\Services\WeChatService;
use App\Models\UserWeixinModel;

class Teacher extends ControllerBase
{

    /** 注册
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function register(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'name_is_required'
            ],
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
                'key' => 'app_id',
                'type' => 'required',
                'error_code' => 'app_id_is_required'
            ],
            [
                'key' => 'org_id',
                'type' => 'integer'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $openId = $this->ci["open_id"];
        if (empty($openId)) {
            return $response->withJson(Valid::addAppErrors([], 'not_found_open_id'), StatusCode::HTTP_OK);
        }
        // todo 验证sms_code

        //验证手机号是否已存在
        $teacher_info = TeacherModelForApp::getTeacherInfo("", $params['mobile']);

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        if (empty($teacher_info["id"])) {

            $teacher_id = TeacherServiceForApp::teacherRegister($params["mobile"], $params["name"]);
            if (empty($teacher_id)) {
                return $response->withJson(Valid::addAppErrors([], 'register_failed'), StatusCode::HTTP_OK);
            }

            $teacher_info = TeacherModelForApp::getTeacherInfo("", $params['mobile']);
        }
        $token = WeChatService::generateToken($teacher_info["id"], WeChatService::USER_TYPE_STUDENT,
            $params["app_id"], $openId);

        // 绑定该用户与微信
        UserWeixinModel::boundUser($openId, $teacher_info["id"], $params["app_id"], WeChatService::USER_TYPE_STUDENT, 1);
        $db->commit();



        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ["token" => $token]
        ], StatusCode::HTTP_OK);
    }

    /** token失效时获取token
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function login(Request $request, Response $response)
    {
        Util::unusedParam($request);

        $openId = $this->ci["open_id"];
        if (empty($openId)) {
            return $response->withJson(Valid::addAppErrors([], 'not_found_open_id'), StatusCode::HTTP_OK);
        }
        $bound_info = UserWeixinModel::getBoundInfoByOpenId($openId);
        // 没有找到该openid的绑定关系
        if (empty($bound_info)) {
            return $response->withJson(Valid::addAppErrors([], 'need_bound'), StatusCode::HTTP_OK);
        }

        $token = WeChatService::generateToken($bound_info["user_id"], WeChatService::USER_TYPE_STUDENT,
            $bound_info["app_id"], $openId);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ["token" => $token]
        ], StatusCode::HTTP_OK);
    }
}
