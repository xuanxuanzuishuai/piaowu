<?php


namespace App\Controllers\API;


use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\RealSharePosterService;
use App\Services\StudentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Ref extends ControllerBase
{
    public function getQrPath(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required'
            ],
            [
                'key' => 'channel_id',
                'type' => 'required',
                'error_code' => 'channel_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        //获取真人小程序码
        $params['poster_id'] = $params['poster_id'] ?? 0;
        try {
            $result = RealSharePosterService::getQrPath($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $result);
    }

    /**
     * 粒子激活统计
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function studentActive(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required'
            ],
            [
                'key' => 'active_type',
                'type' => 'required',
                'error_code' => 'active_type_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $result = StudentService::studentLoginActivePushQueue(Constants::REAL_APP_ID, $params['student_id'], $params['active_type']);
        return HttpHelper::buildResponse($response, $result);
    }
}