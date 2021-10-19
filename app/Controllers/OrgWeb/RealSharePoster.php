<?php
/**
 * Created by PhpStorm.
 * User: sunchanghui
 * Date: 2021-09-02 10:32:45
 * Time: 6:31 PM
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Models\RealSharePosterModel;
use App\Services\RealSharePosterService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class RealSharePoster extends ControllerBase
{
    /**
     * 上传截图列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            if (empty($params['activity_id'])) {
                throw new RunTimeException(['activity_id_cant_not_null']);
            }
            $params['type'] = RealSharePosterModel::TYPE_WEEK_UPLOAD;
            list($posters, $totalCount) = RealSharePosterService::sharePosterList($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        
        return HttpHelper::buildResponse($response, [
            'posters' => $posters,
            'total_count' => $totalCount
        ]);
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
            $params['employee_id']  = $employeeId;
            $result = RealSharePosterService::approvalPoster($params['poster_ids'], $params);
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
            $params['employee_id']  = $employeeId;
            $result = RealSharePosterService::refusedPoster($params['poster_id'], $params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        
        return HttpHelper::buildResponse($response, $result);
    }

    public static function parseUnique(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'unique_code',
                'type' => 'required',
                'error_code' => 'unique_code_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $type = Constants::REAL_APP_ID;
            $data = RealSharePosterService::parseUnique($params['unique_code'], $type);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }
}
