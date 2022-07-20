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
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\OperationActivityModel;
use App\Models\RealSharePosterModel;
use App\Services\Activity\LimitTimeActivity\LimitTimeActivityAdminService;
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
            if (!empty($params['activity_type']) && $params['activity_type'] == OperationActivityModel::SHARE_POSTER_ACTIVITY_TYPE_LIMIT) {
                list($page, $count) = Util::formatPageCount($params);
                $params['app_id'] = Constants::REAL_APP_ID;
                $params['verify_status'] = $params['poster_status'];
                $data = LimitTimeActivityAdminService::getActivitySharePosterList($params, $page, $count);
                $posters = $data['list'] ?? [];
                $totalCount = $data['total_count'] ?? 0;
                foreach ($posters as &$item) {
                    $item['img_url'] = $item['format_share_poster_url'];
                    $item['poster_status'] = $item['verify_status'];
                    $item['create_time'] = $item['format_create_time'];
                    $item['check_time'] = $item['format_verify_time'];
                    $item['operator_id'] = $item['verify_user'];
                    $item['status_name'] = $item['format_verify_status'];
                    $item['operator_name'] = $item['format_verify_user'];
                    $item['reason'] = $item['verify_reason'];
                    $item['uuid'] = $item['student_uuid'];
                }
                unset($item);
            } else {
                $params['type'] = RealSharePosterModel::TYPE_WEEK_UPLOAD;
                list($posters, $totalCount) = RealSharePosterService::sharePosterList($params);
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, [
            'posters'     => $posters,
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
            ],
            [
                'key' => 'activity_id',
                'type' => 'required',
                'error_code' => 'activity_id_is_required'
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
            if (!empty($params['activity_type']) && $params['activity_type'] == OperationActivityModel::SHARE_POSTER_ACTIVITY_TYPE_LIMIT) {
                $params['app_id'] = Constants::REAL_APP_ID;
                $result = LimitTimeActivityAdminService::approvalPoster($params['poster_ids'], $params);
            } else {
                $result = RealSharePosterService::approvalPoster($params['poster_ids'], $params);
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
                'key'        => 'poster_id',
                'type'       => 'required',
                'error_code' => 'poster_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $params['employee_id'] = self::getEmployeeId();
            if (!empty($params['activity_type']) && $params['activity_type'] == OperationActivityModel::SHARE_POSTER_ACTIVITY_TYPE_LIMIT) {
                $params['app_id'] = Constants::REAL_APP_ID;
                $result = LimitTimeActivityAdminService::refusedPoster($params['poster_id'], $params);
            } else {
                $result = RealSharePosterService::refusedPoster($params['poster_id'], $params);
            }
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
