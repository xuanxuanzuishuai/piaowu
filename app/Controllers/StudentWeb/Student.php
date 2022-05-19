<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021-03-08
 * Time: 14:39
 */

namespace App\Controllers\StudentWeb;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Services\CommonServiceForApp;
use App\Services\RecallLandingService;
use App\Services\RtActivityService;
use App\Services\StudentService;
use App\Services\UserService;
use App\Services\WechatTokenService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Student extends ControllerBase
{
    /**
     * 学生地址列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function addressList(Request $request, Response $response)
    {
        $tokenInfo = $this->ci['user_info'];
        $userInfo = StudentService::getUuid($tokenInfo['app_id'], $tokenInfo['user_id']);

        $erp = new Erp();
        $result = $erp->getStudentAddressList($userInfo['uuid']);
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
     * @return Response
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
                'key' => 'is_default',
                'type' => 'required',
                'error_code' => 'address_default_is_required',
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $params['uuid'] = $params['uuid'] ?? '';
        $params['default'] = $params['is_default'];
        unset($params['is_default']);
        $student = $this->ci['user_info'];
        if (empty($params['uuid']) && !empty($student['user_id'])) {
            $tokenInfo = $this->ci['user_info'];
            $studentInfo = StudentService::getUuid($tokenInfo['app_id'], $tokenInfo['user_id']);
            $params['uuid'] = $studentInfo['uuid'] ?? '';
        }

        $erp = new Erp();
        $result = $erp->modifyStudentAddress($params);
        return $response->withJson($result, StatusCode::HTTP_OK);
    }

    /**
     * 解除绑定
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function unbind(Request $request, Response $response)
    {
        $params = $request->getParams();
        $student = $this->ci['user_info'];
        $userType = $params['user_type'] ?: DssUserWeiXinModel::USER_TYPE_STUDENT;
        $appId = DssUserWeiXinModel::dealAppId($params['app_id']);
        WechatTokenService::delTokenByUserId($student['user_id'], $userType, $appId);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => []
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getAssistant(Request $request, Response $response)
    {
        $student = $this->ci['user_info'];
        $assistantInfo = [];
        if (!empty($student['user_id'])) {
            $assistantInfo = DssStudentModel::getAssistantInfo($student['user_id']);
        }
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $assistantInfo
        ], StatusCode::HTTP_OK);
    }

    /**
     * 体验转年卡页面
     * 按钮点击和进入页面助教短信
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws \Exception
     */
    public function webPageEvent(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $student = $this->ci['user_info'];
            $event = $params['event'] ?? null;
            if (!is_null($event) && !empty($student['user_id'])) {
                RecallLandingService::webEventMessage($event, $student['user_id'], $params);
            }
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 课管主动建立转介绍关系-用户端首页
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function activityIndex(Request $request, Response $response)
    {
        try {
            $rules = [
                [
                    'key' => 'qr_id',
                    'type' => 'required',
                    'error_code' => 'qr_id_is_required',
                ],
            ];

            $params = $request->getParams();
            $result = Valid::appValidate($params, $rules);
            if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            $data = RtActivityService::activityIndex($params);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 发送验证码
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sendCode(Request $request, Response $response)
    {
        try {
            $rules = [
                [
                    'key' => 'qr_id',
                    'type' => 'required',
                    'error_code' => 'qr_id_is_required',
                ],
            ];

            $params = $request->getParams();
            $result = Valid::appValidate($params, $rules);
            if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            $qrInfo = RtActivityService::getQrInfoById($params['qr_id']);
            $errorCode = CommonServiceForApp::sendValidateCode($qrInfo['invited_mobile'], CommonServiceForApp::SIGN_WX_STUDENT_APP);
            if (!empty($errorCode)) {
                $result = Valid::addAppErrors([], $errorCode);
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }


    /**
     * 0元下单
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function freeOrder(Request $request, Response $response)
    {
        try {
            $rules = [
                [
                    'key' => 'sms_code',
                    'type' => 'required',
                    'error_code' => 'qr_id_is_required',
                ],
                [
                    'key' => 'qr_id',
                    'type' => 'required',
                    'error_code' => 'qr_id_is_required',
                ]
            ];

            $params = $request->getParams();
            $result = Valid::appValidate($params, $rules);
            if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            $qrInfo = RtActivityService::getQrInfoById($params['qr_id']);
            if (!CommonServiceForApp::checkValidateCode($qrInfo["invited_mobile"], $params["sms_code"])) {
                return $response->withJson(Valid::addAppErrors([], 'validate_code_error'), StatusCode::HTTP_OK);
            }
            $qrInfo['mobile'] = $qrInfo['invited_mobile'];
            RtActivityService::handleFreeOrder($qrInfo);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }


    /**
     * 获取助教信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getAssistantInfo(Request $request, Response $response)
    {
        try {
            $rules = [
                [
                    'key' => 'qr_id',
                    'type' => 'required',
                    'error_code' => 'qr_id_is_required',
                ]
            ];
            $params = $request->getParams();
            $result = Valid::appValidate($params, $rules);
            if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            $result = RtActivityService::getAssistantInfo($params);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $result);
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
                'key'        => 'mobile',
                'type'       => 'required',
                'error_code' => 'mobile_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $errorCode = CommonServiceForApp::sendValidateCode($params['mobile'], CommonServiceForApp::SIGN_WX_STUDENT_APP);
        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        return HttpHelper::buildResponse($response, []);
    }


    /**
     * 数据记录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function whaleDataRecord(Request $request, Response $response)
    {
        try {
            $rules  = [
                [
                    'key'        => 'mobile',
                    'type'       => 'required',
                    'error_code' => 'mobile_is_required'
                ],
                [
                    'key'        => 'sms_code',
                    'type'       => 'required',
                    'error_code' => 'sms_code_is_required',
                ],
            ];
            $params = $request->getParams();
            $result = Valid::appValidate($params, $rules);
            if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            if (!CommonServiceForApp::checkValidateCode($params["mobile"], $params["sms_code"])) {
                return $response->withJson(Valid::addAppErrors([], 'validate_code_error'), StatusCode::HTTP_OK);
            }
            RtActivityService::whaleDataRecord($params);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * DSS - 获取学生是否能够购买指定课包，如果系统判定的重复用户购买指定课包时会返回其他课包
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function checkStudentIsRepeat(Request $request, Response $response)
    {
        $rules  = [
            [
                'key'        => 'pkg',
                'type'       => 'required',
                'error_code' => 'pkg_is_required'
            ],
            [
                'key'        => 'uuid',
                'type'       => 'required',
                'error_code' => 'uuid_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = UserService::getDssStudentRepeatBuyPkg($params['uuid'], $params['pkg']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }
}
