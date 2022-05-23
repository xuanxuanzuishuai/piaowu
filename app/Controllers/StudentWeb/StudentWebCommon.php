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
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\NewSMS;
use App\Libs\Valid;
use App\Services\AreaService;
use App\Services\CommonServiceForApp;
use App\Services\StudentService;
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
                'error_code' => 'channel_id_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        // 验证手机验证码
        if (!empty($params['code']) && !CommonServiceForApp::checkValidateCode($params['mobile'], $params['code'], $params['country_code'] ?? NewSMS::DEFAULT_COUNTRY_CODE)) {
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
        if (empty($params)){
            $list = AreaService::getAreaByParentCode('100000');
        }elseif (!empty($params['province_code'])){
            $list[] = AreaService::getByCode($params['province_code']);
        }
        $data = [];
        if (!empty($list)){
            foreach ($list as $value){
                $data[] = [
                    'province_code'=>$value['code'],
                    'province_name'=>$value['name'],
                ];
            }
        }
        return HttpHelper::buildResponse($response, $data);
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
        if (!empty($params['province_code'])){
            $list = AreaService::getAreaByParentCode($params['province_code']);
        }elseif (!empty($params['city_code'])){
            $list[] = AreaService::getByCode($params['city_code']);
        }

        $data = [];
        if (!empty($list)){
            foreach ($list as $value){
                $data[] = [
                    'city_code'=>$value['code'],
                    'city_name'=>$value['name'],
                ];
            }
        }
        return HttpHelper::buildResponse($response, $data);
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
        if (!empty($params['city_code'])){
            $list = AreaService::getAreaByParentCode($params['city_code']);
        }elseif (!empty($params['district_code'])){
            $list[] = AreaService::getByCode($params['district_code']);
        }

        $data = [];
        if (!empty($list)){
            foreach ($list as $value){
                $data[] = [
                    'district_code'=>$value['code'],
                    'district_name'=>$value['name'],
                ];
            }
        }
        return HttpHelper::buildResponse($response, $data);
    }

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
}