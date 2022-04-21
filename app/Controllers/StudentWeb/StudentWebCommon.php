<?php
/**
 * Created by PhpStorm.
 * User: liuguokun
 * Date: 2022/4/12
 * Time: 3:11 下午
 */

namespace App\Controllers\StudentWeb;


use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\NewSMS;
use App\Libs\Valid;
use App\Services\AreaService;
use App\Services\CommonServiceForApp;
use App\Services\StudentWebCommonService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class StudentWebCommon extends ControllerBase
{
    /**
     * 用户登录接口
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public function login(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'app_id',
                'type' => 'required',
                'error_code' => 'app_id_is_required',
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
                'key' => 'code',
                'type' => 'required',
                'error_code' => 'code_is_required',
            ],
            [
                'key' => 'channel_id',
                'type' => 'required',
                'error_code' => 'app_id_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        // 验证手机验证码
        if (!empty($params['sms_code']) && !CommonServiceForApp::checkValidateCode($params['mobile'], $params['code'], $params['country_code'] ?? NewSMS::DEFAULT_COUNTRY_CODE)) {
            return $response->withJson(Valid::addAppErrors([], 'validate_code_error'), StatusCode::HTTP_OK);
        }
        try {
            //获取登录用户信息
            $loginInfo = StudentWebCommonService::login($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response,$loginInfo);
    }

    /**
     * 获取国家列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function countryList(/** @noinspection PhpUnusedParameterInspection */Request $request, Response $response)
    {
        $list = AreaService::countryList();
        return HttpHelper::buildResponse($response, $list);
    }

    /**
     * 获取省列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function provinceList(Request $request, Response $response)
    {
        $params = $request->getParams();
        $list = AreaService::provinceList($params);
        return HttpHelper::buildResponse($response, $list);
    }

    /**
     * 获取市列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function cityList(Request $request, Response $response)
    {
        $params = $request->getParams();
        $list = AreaService::cityList($params);
        return HttpHelper::buildResponse($response, $list);
    }

    /**
     * 获取区列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function districtList(Request $request, Response $response)
    {
        $params = $request->getParams();
        $list = AreaService::districtList($params);
        return HttpHelper::buildResponse($response, $list);
    }
}