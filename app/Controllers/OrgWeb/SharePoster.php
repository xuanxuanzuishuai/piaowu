<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/12/18
 * Time: 17:41
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\SharePosterModel;
use App\Services\SharePosterService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class SharePoster extends ControllerBase
{
    /**
     * 上传截图列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        $params = $request->getParams();
        $params['type'] = SharePosterModel::TYPE_CHECKIN_UPLOAD;
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        list($posters, $totalCount) = SharePosterService::sharePosterList($params);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'posters' => $posters,
                'total_count' => $totalCount
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 审核通过
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function approved(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'poster_ids',
                'type' => 'required',
                'error_code' => 'poster_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $employeeId = self::getEmployeeId();

        try {
            $result = SharePosterService::approvedCheckin($params['poster_ids'], $employeeId);
            if (!empty($result)) {
                throw new RunTimeException(['invalid_time_exists']);
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $result);
    }

    /**
     * 审核拒绝
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function refused(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'poster_id',
                'type' => 'required',
                'error_code' => 'poster_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $employeeId = self::getEmployeeId();

        try {
            $result = SharePosterService::refusedCheckin($params['poster_id'], $employeeId, $params['reason'], $params['remark']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $result);
    }
}