<?php

namespace App\Controllers\StudentWX;


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
     * 打谱进度查询和打谱权限校验接口
     */
    public function scheduleQuery(Request $request, Response $response)
    {
        $studentId = $this->ci['user_info']['user_id'];
        $data = MakeOperaService::getStudentAndSwoInfo($studentId);
        return $response->withJson([
            'code' => 0,
            'data' => $data
        ], StatusCode::HTTP_OK);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 打谱申请接口
     */
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
        $studentId = $this->ci['user_info']['user_id'];
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
}