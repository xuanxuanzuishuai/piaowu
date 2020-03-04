<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/21
 * Time: 11:07
 */

namespace App\Controllers\TeacherWX;

use App\Controllers\ControllerBase;
use App\Controllers\StudentWX\Common;
use App\Libs\Valid;
use App\Models\OrganizationModel;
use App\Models\QRCodeModel;
use App\Models\TeacherOrgModel;
use App\Services\CommonServiceForApp;
use App\Services\QRCodeService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Libs\MysqlDB;
use App\Models\TeacherModelForApp;
use App\Services\TeacherServiceForApp;
use App\Services\TeacherOrgService;
use App\Services\WeChatService;
use App\Models\UserWeixinModel;
use App\Libs\UserCenter;

class Teacher extends ControllerBase
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
                'key' => 'org_id',
                'type' => 'integer'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $app_id = UserCenter::AUTH_APP_ID_AIPEILIAN_TEACHER;
        $old_token = $this->ci["token"];
        if (!empty($old_token)){
            WeChatService::deleteToken($old_token);
        }
        $openId = $this->ci["open_id"];
        if (!CommonServiceForApp::checkValidateCode($params["mobile"], $params["sms_code"])) {
            return $response->withJson(Valid::addAppErrors([], 'validate_code_error'), StatusCode::HTTP_OK);
        }

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
        }else{
            if (!empty($params["name"])){
                // 更新名字
                TeacherModelForApp::updateRecord($teacher_info["id"], ["name" => $params["name"]]);
            }
        }

        if (!empty($openId)) {
            // 绑定该用户与微信
            UserWeixinModel::boundUser($openId, $teacher_info["id"], $app_id, WeChatService::USER_TYPE_TEACHER, 1);
        }

        if (!empty($params["org_id"])) {
            // 绑定机构,如果机构作废则不会绑定了
            TeacherOrgService::boundTeacher($params["org_id"], $teacher_info["id"]);

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

        $app_id = UserCenter::AUTH_APP_ID_AIPEILIAN_TEACHER;
        $openId = $this->ci["open_id"];
        if (empty($openId)) {
            return $response->withJson(Valid::addAppErrors([], 'need_bound'), StatusCode::HTTP_OK);
        }
        // TODO: 该文件对应老师微信迁移到aitob 已废弃
        $bound_info = UserWeixinModel::getBoundInfoByOpenId($openId);
        // 没有找到该openid的绑定关系
        if (empty($bound_info)) {
            return $response->withJson(Valid::addAppErrors([], 'need_bound'), StatusCode::HTTP_OK);
        }

        $token = WeChatService::generateToken($bound_info["user_id"], WeChatService::USER_TYPE_TEACHER,
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
            CommonServiceForApp::SIGN_WX_TEACHER_APP);
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
     * 获取邀请二维码
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getInviteQrCode(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'org_id',
                'type' => 'required',
                'error_code' => 'org_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $user_id = $this->ci['user_info']['user_id'];
        $ret = QRCodeService::getOrgStudentBindRefereeQR($params["org_id"],
            QRCodeModel::REFEREE_TYPE_TEACHER, $user_id);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $ret
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取老师绑定结构列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getBindOrgList(Request $request, Response $response)
    {

        $user_id = $this->ci['user_info']['user_id'];
//        $user_id = 13150;
        $records = TeacherOrgModel::getBoundList($user_id, TeacherOrgModel::STATUS_NORMAL);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $records
        ], StatusCode::HTTP_OK);
    }
}
