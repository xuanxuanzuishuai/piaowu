<?php
/**
 * 清晨5日打卡
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\MorningReferral\MorningClockActivityManageService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class MorningClockActivity extends ControllerBase
{
    /**
     * 获取截图审核列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getSharePosterList(Request $request, Response $response)
    {

        $params = $request->getParams();
        $params['employee_id'] = self::getEmployeeId();
        try {
            $data = MorningClockActivityManageService::getSharePosterList($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 审核拒绝
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sharePosterRefused(Request $request, Response $response)
    {

        $rules = [
            [
                'key'        => 'poster_id',
                'type'       => 'required',
                'error_code' => 'poster_id_is_required',
            ],
        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $params['employee_id'] = self::getEmployeeId();
        try {
            MorningClockActivityManageService::sharePosterRefused($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 审核通过
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sharePosterApproved(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'poster_ids',
                'type'       => 'required',
                'error_code' => 'poster_ids_is_required',
            ],
        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $params['employee_id'] = self::getEmployeeId();
        try {
            MorningClockActivityManageService::sharePosterApproved($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 获取清晨截图审核和红包审核列表的下拉选项
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function dropDown(Request $request, Response $response)
    {

        $rules = [
            [
                'key'        => 'dp',
                'type'       => 'required',
                'error_code' => 'dp_type_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $data = MorningClockActivityManageService::dropDown($params['dp']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }
}