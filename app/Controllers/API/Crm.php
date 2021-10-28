<?php
/**
 * 接收crm服务相关请求
 */

namespace App\Controllers\API;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Services\ErpReferralService;
use App\Services\RealSharePosterService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Crm extends ControllerBase
{
    /**
     * 推荐人信息列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function refereeList(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'uuids',
                'type' => 'required',
                'error_code' => 'ids_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $res = ErpReferralService::refereeList($params);
        } catch (RunTimeException $e) {
            SimpleLogger::info(__FUNCTION__, ['params' => $params, 'err' => $e->getData()]);
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $res);
    }

    /**
     * 员工代替学生生成学生转介绍海报
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function replaceStudentCreatePoster(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key' => 'app_id',
                'type' => 'required',
                'error_code' => 'app_id_is_required'
            ],
            [
                'key' => 'employee_uuid',
                'type' => 'required',
                'error_code' => 'employee_uuid_is_required'
            ],
            [
                'key' => 'student_uuid',
                'type' => 'required',
                'error_code' => 'student_uuid_is_required'
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
        $params['from_service'] = $request->getHeaderLine('service');
        $data = RealSharePosterService::replaceStudentCreatePoster($params);
        return HttpHelper::buildResponse($response, $data);
    }
}
