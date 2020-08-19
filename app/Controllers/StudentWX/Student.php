<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/23
 * Time: 15:41
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\AliContentCheck;
use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\EmployeeModel;
use App\Models\StudentModel;
use App\Services\AIPlayRecordService;
use App\Services\CollectionService;
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
use App\Models\ReviewCourseModel;


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
                'key' => 'referee_type',
                'type' => 'integer'
            ],
            [
                'key' => 'referee_id',
                'type' => 'integer'
            ],
            [
                'key' => 'wx_code',
                'type' => 'required',
                'error_code' => 'wx_code_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $oldToken = $request->getHeader('token');
        $oldToken = $oldToken[0] ?? null;
        if (!empty($oldToken)) {
            WeChatService::deleteToken($oldToken);
        }

        $app_id = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
        if (empty($params['sms_code']) && empty($params['password'])) {
            return $response->withJson(Valid::addAppErrors([], 'please_check_the_parameters'), StatusCode::HTTP_OK);
        } elseif (!empty($params['sms_code']) && !CommonServiceForApp::checkValidateCode($params["mobile"], $params["sms_code"], $params['country_code'])) {
            return $response->withJson(Valid::addAppErrors([], 'incorrect_mobile_phone_number_or_verification_code'), StatusCode::HTTP_OK);
        } elseif (!empty($params['password']) && !CommonServiceForApp::checkPassword($params['mobile'], $params['password'], $params['country_code'])) {
            return $response->withJson(Valid::addAppErrors([], 'password_error'), StatusCode::HTTP_OK);
        }

        //验证手机号是否已存在
        $student_info = StudentModelForApp::getStudentInfo("", $params['mobile']);

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        if (empty($student_info["id"])) {
            list($student_id) = StudentServiceForApp::studentRegister(
                $params["mobile"],
                StudentModel::CHANNEL_WE_CHAT_SCAN,
                $params["name"],
                null,
                $params['country_code']
            );
            if (empty($student_id)) {
                return $response->withJson(Valid::addAppErrors([], 'register_failed'), StatusCode::HTTP_OK);
            }

            // 转介绍
            if (!empty($params["referee_id"]) and !empty($params["referee_type"])) {
                UserRefereeModel::insertReferee($params["referee_id"], $student_id);
            }

            $student_info = StudentModelForApp::getStudentInfo("", $params['mobile']);
        }

        $userType = UserWeixinModel::USER_TYPE_STUDENT;
        $data = WeChatService::getWeixnUserOpenIDAndAccessTokenByCode($params['wx_code'], $app_id, $userType);
        if (empty($data) || empty($data['openid'])) {
            return $response->withJson(Valid::addAppErrors([], 'can_not_obtain_open_id'));
        }

        $openId = $data['openid'];
        // 绑定该用户与微信
        UserWeixinModel::boundUser($openId, $student_info["id"], $app_id, $userType, 1);

        $db->commit();

        // 已付费体验课用戶發送班級二維碼
        if ($student_info['has_review_course'] == ReviewCourseModel::REVIEW_COURSE_49 && !empty($student_info['collection_id'])) {

            $config = [
                'app_id' => $_ENV['STUDENT_WEIXIN_APP_ID'],
                'app_secret' => $_ENV['STUDENT_WEIXIN_APP_SECRET'],
            ];
            $wx = WeChatMiniPro::factory($config);
            if (empty($wx)) {
                SimpleLogger::error('wx mini pro create fail', ['config' => $config]);
            }

            $url = $_ENV['SMS_FOR_EXPERIENCE_CLASS_REGISTRATION']."?c=".$student_info['collection_id'];
            $textContext = '🎹欢迎加入小叶子智能陪练！'. PHP_EOL.'👉<a href="' . $url . '">请点击这里完成开班前准备</a>';
            $wx->sendText($openId, $textContext);
        }

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

        $bound_info = UserWeixinModel::getBoundInfoByOpenId(
            $openId,
            UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            WeChatService::USER_TYPE_STUDENT,
            UserWeixinModel::BUSI_TYPE_STUDENT_SERVER
        );

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
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $errorCode = CommonServiceForApp::sendValidateCode($params['mobile'], CommonServiceForApp::SIGN_WX_STUDENT_APP, $params['country_code']);
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

        $playSum = AIPlayRecordService::getStudentTotalSumData($user_id);

        $expire_date = $student_info["sub_end_date"];
        $sub_status = 0;
        if (empty($expire_date) or (int)$student_info["sub_status"] == 0){
            $expire_date = "";
        } else {
            $expire_date = substr($expire_date, 0, 4) . "-" .
                substr($expire_date, 4, 2) . "-" . substr($expire_date, 6, 2);
            $expire_time = $expire_date . " 23:59:59";
            if (strtotime($expire_time) > time()){
                $sub_status = 1;
            }
        }
        $account_info = [
            "mobile" => substr($student_info["mobile"], 0, 3) . "****" .
                substr($student_info["mobile"], 7, 4),
            "name" => $student_info["name"],
            "thumb" => AliOSS::signUrls($student_info["thumb"]),
            "lesson_num" => $playSum['lesson_count'],
            "duration" => $playSum['sum_duration'],
            "expired_date" => $expire_date,
            "sub_status" => $sub_status,
            "open_id" => $this->ci['open_id'],
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
        $params = $request->getParams();
        try {
            $update_info = [];
            if (!empty($params["thumb"])){
                //检测图片是否合规
                $checkResponse = (new AliContentCheck())->checkImgLegal(AliOSS::signUrls($params['thumb']));
                if (!empty($checkResponse)) {
                    if (in_array(AliContentCheck::ILLEGAL_RESULT, array_values($checkResponse))) {
                        throw new RunTimeException(['illegal_img']);
                    }
                }
                $update_info["thumb"] = $params["thumb"];
            }
            if (!empty($params["name"])){
                $update_info["name"] = $params["name"];
            }
            $user_id = $this->ci['user_info']['user_id'];
            StudentModelForApp::updateRecord($user_id, $update_info);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    public function giftCode(Request $request, Response $response)
    {
        Util::unusedParam($request);

        $studentId = $this->ci['user_info']['user_id'];
        $ret = StudentService::selfGiftCode($studentId);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'gift_codes' => $ret
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 解除绑定
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function unbind(Request $request, Response $response)
    {
        // 删除token
        $oldToken = $request->getHeader('token');
        $oldToken = $oldToken[0] ?? null;
        if (!empty($oldToken)) {
            WeChatService::deleteToken($oldToken);
        }

        $appId = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
        $userType = WeChatService::USER_TYPE_STUDENT;
        $busiType = UserWeixinModel::BUSI_TYPE_STUDENT_SERVER;
        $openId = $this->ci["open_id"];
        $studentId = $this->ci['user_info']['user_id'];

        // 解绑微信
        $boundInfo = UserWeixinModel::getBoundInfoByOpenId($openId, $appId, $userType, $busiType);
        if (!empty($boundInfo)) {
            UserWeixinModel::unboundUser($openId, $studentId, $appId, $userType, $busiType);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => []
        ], StatusCode::HTTP_OK);
    }

    /**
     * 学生地址列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function addressList(Request $request, Response $response)
    {
        $studentId = $this->ci['user_info']['user_id'];
        $student = StudentModelForApp::getById($studentId);

        $erp = new Erp();
        $result = $erp->getStudentAddressList($student['uuid']);
        if (empty($result) || $result['code'] != 0) {
            $errorCode = $result['errors'][0]['err_no'] ?? 'erp_request_error';
            return $response->withJson(Valid::addAppErrors([], $errorCode), StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'address_list' => $result['data']['list']
            ]
        ], StatusCode::HTTP_OK);

    }

    /**
     * 添加、修改地址
     * @param Request $request
     * @param Response $response
     * @return null|Response
     */
    public function modifyAddress(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'student_name_is_required',
            ],
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'mobile_is_required',
            ],
            [
                'key' => 'mobile',
                'type' => 'regex',
                'value' => Constants::MOBILE_REGEX,
                'error_code' => 'student_mobile_format_is_error'
            ],
            [
                'key' => 'country_code',
                'type' => 'required',
                'error_code' => 'country_code_is_required',
            ],
            [
                'key' => 'province_code',
                'type' => 'required',
                'error_code' => 'province_code_is_required'
            ],
            [
                'key' => 'city_code',
                'type' => 'required',
                'error_code' => 'city_code_is_required'
            ],
            [
                'key' => 'district_code',
                'type' => 'required',
                'error_code' => 'district_code_is_required'
            ],
            [
                'key' => 'address',
                'type' => 'required',
                'error_code' => 'student_address_is_required',
            ],
            [
                'key' => 'default',
                'type' => 'required',
                'error_code' => 'address_default_is_required',
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $studentId = $this->ci['user_info']['user_id'];
        $student = StudentModelForApp::getById($studentId);
        $params['uuid'] = $student['uuid'];

        $erp = new Erp();
        $result = $erp->modifyStudentAddress($params);
        if (empty($result) || $result['code'] != 0) {
            $errorCode = $result['errors'][0]['err_no'] ?? 'erp_request_error';
            return $response->withJson(Valid::addAppErrors([], $errorCode), StatusCode::HTTP_OK);
        }
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => []
        ], StatusCode::HTTP_OK);

    }

    public function classInformation(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'class_id',
                'type' => 'required',
                'error_code' => 'class_id_is_required',
            ],
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $collectionData = CollectionService::getStudentCollectionDetailByID($params['class_id']);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $collectionData
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取课管对外信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getCourseUserInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'course_id',
                'type' => 'required',
                'error_code' => 'course_id_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $courseUserInfo = EmployeeModel::getRecord(['id' => $params['course_id']], ['wx_nick', 'wx_thumb', 'wx_qr']);
        $courseUserInfo['wx_thumb'] = AliOSS::signUrls($courseUserInfo['wx_thumb']);
        $courseUserInfo['wx_qr'] = AliOSS::signUrls($courseUserInfo['wx_qr']);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $courseUserInfo
        ], StatusCode::HTTP_OK);
    }
}
