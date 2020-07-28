<?php


namespace App\Controllers\OrgWeb;


use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\MakeOperaService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class MakeOpera extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 根据手机号获取用户信息
     */
    public function userInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'mobile_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $userInfo = MakeOperaService::getStudentInfo($params);
        return $response->withJson([
            'code' => 0,
            'data' => $userInfo
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 打谱进度查询和打谱权限校验接口
     */
    public function scheduleQuery(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data = MakeOperaService::getStudentAndSwoInfo($params['student_id']);
        return $response->withJson([
            'code' => 0,
            'data' => $data
        ], StatusCode::HTTP_OK);
    }


    public function operaApply(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'opera_name',
                'type'       => 'lengthMax',
                'value'      => 50,
                'error_code' => 'opera_name_elt_127'
            ],
            [
                'key'        => 'opera_images',
                'type'       => 'array',
                'error_code' => 'opera_images_must_be_array'
            ],
            [
                'key'        => 'student_id',
                'type'       => 'integer',
                'error_code' => 'student_id_must_integer'
            ],
            [
                'key'        => 'creator_id',
                'type'       => 'integer',
                'error_code' => 'creator_id_must_integer'
            ],
            [
                'key'        => 'creator_type',
                'type'       => 'integer',
                'error_code' => 'creator_type_must_integer'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $swoId = MakeOperaService::getSwoId($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, ["swo_id"=>$swoId]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 打谱申请撤销接口
     */
    public function cancel(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'swo_id',
                'type' => 'required',
                'error_code' => 'swo_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $params['user_id'] = self::getEmployeeId();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $updateSwo = MakeOperaService::cancelSwo($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $updateSwo);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 获取用户打谱申请历史记录接口
     */
    public function history(Request $request, Response $response)
    {
        $studentId = $request->getParam('student_id');
        $params = $request->getParams();
        list($list,$totalNum) = MakeOperaService::getHistoryList($studentId, $params['page'], $params['limit']);
        return $response->withJson([
            'code' => 0,
            'data' => [
                'count' => $totalNum,
                'list' => $list
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 曲谱详情页接口
     */
    public function operaDetail(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'swo_id',
                'type' => 'required',
                'error_code' => 'swo_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data = MakeOperaService::getOperaInfo($params['swo_id']);
        return $response->withJson([
            'code' => 0,
            'data' => $data
        ], StatusCode::HTTP_OK);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 工单列表查询接口
     */
    public function swoList(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($list,$totalNum) = MakeOperaService::getSwoList($params);
        return $response->withJson([
            'code' => 0,
            'data' => [
                'count' => $totalNum,
                'list' => $list
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 获取助教，课管，曲谱制作人和配置人列表
     */
    public function getRoleList(Request $request, Response $response)
    {
        $employee['id'] = self::getEmployeeId();
        $employee['role_id'] = self::getRoleId();
        $makerAndConfigList = MakeOperaService::getMakerConfigList($employee);
        return $response->withJson([
            'code' => 0,
            'data' => $makerAndConfigList
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 分配制作人和配置人
     */
    public function distributeMakerConfigure(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'swo_ids',
                'type'       => 'array',
                'error_code' => 'swo_ids_must_be_array'
            ],
            [
                'key'        => 'type',
                'type'       => 'integer',
                'error_code' => 'type_must_integer'
            ],
            [
                'key'        => 'target_id',
                'type'       => 'required',
                'error_code' => 'target_id_must_required'
            ],
            [
                'key'        => 'target_id',
                'type'       => 'integer',
                'error_code' => 'target_id_must_integer'
            ]
        ];
        $params = $request->getParams();
        $params['user_id'] = self::getEmployeeId();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $ret = MakeOperaService::distributeTask($params);
        return HttpHelper::buildResponse($response, ["updateRows"=>$ret]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 曲谱审核接口
     */
    public function swoApprove(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'swo_id',
                'type'       => 'integer',
                'error_code' => 'swo_id_must_integer'
            ],
            [
                'key'        => 'type',
                'type'       => 'integer',
                'error_code' => 'type_must_integer'
            ]
        ];
        $params = $request->getParams();
        $params['user_id'] = self::getEmployeeId();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            MakeOperaService::swoApprove($params);
        }catch (RunTimeException $e){
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response,[]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 曲谱开始制作接口
     */
    public function makeStart(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'swo_id',
                'type'       => 'integer',
                'error_code' => 'swo_id_must_integer'
            ]
        ];
        $params = $request->getParams();
        $params['user_id'] = self::getEmployeeId();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            MakeOperaService::start($params);
        }catch (RunTimeException $e){
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response,[]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 曲谱制作完成接口
     */
    public function makeEnd(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'swo_id',
                'type'       => 'required',
                'error_code' => 'swo_id_must_required'
            ],
            [
                'key' => 'text_name',
                'type' => 'required',
                'error_code' => 'text_name_required'
            ],
            [
                'key' => 'opera_name',
                'type' => 'required',
                'error_code' => 'opera_name_required'
            ],
            [
                'key'        => 'opera_config_id',
                'type'       => 'required',
                'error_code' => 'opera_config_id_must_required'
            ],
        ];
        $params = $request->getParams();
        $params['user_id'] = self::getEmployeeId();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            MakeOperaService::complete($params);
        }catch (RunTimeException $e){
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response,[]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 启动曲谱接口
     */
    public function operaUse(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'swo_id',
                'type'       => 'integer',
                'error_code' => 'swo_id_must_integer'
            ],
            [
                'key' => 'view_guidance',
                'type' => 'required',
                'error_code' => 'view_guidance_required'
            ]
        ];
        $params = $request->getParams();
        $params['user_id'] = self::getEmployeeId();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            MakeOperaService::useStart($params);
        }catch (RunTimeException $e){
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response,[]);
    }
}