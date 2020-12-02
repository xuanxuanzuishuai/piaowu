<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/23
 * Time: 6:33 PM
 */

namespace App\Controllers\API;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\ReferralActivityService;
use App\Libs\Exceptions\RunTimeException;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Dss extends ControllerBase
{
    /**
     * 获取可生成海报的活动列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function activeList(Request $request, Response $response)
    {
        $params = $request->getParams();
        $result = ReferralActivityService::getActiveList($params);

        return HttpHelper::buildResponse($response, $result);
    }

    /**
     * 获取活动海报
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getPoster(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'activity_id',
                'type'       => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key'        => 'employee_id',
                'type'       => 'required',
                'error_code' => 'employee_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $activity = ReferralActivityService::getEmployeePoster($params['activity_id'], $params['employee_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $activity);
    }
}