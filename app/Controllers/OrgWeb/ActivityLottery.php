<?php /** @noinspection ALL */

/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2022/03/12
 * Time: 10:41
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\Activity\Lottery\LotteryAdminService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class ActivityLottery extends ControllerBase
{
    /**
     * 添加/编辑活动
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function addOrUpdate(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key'        => 'op_activity_id',
                'type'       => 'integer',
                'error_code' => 'op_activity_id_is_integer'
            ],
            [
                'key'        => 'op_activity_id',
                'type'       => 'min',
                'value'      => 1,
                'error_code' => 'op_activity_id_is_integer'
            ],
            [
                'key'        => 'name',
                'type'       => 'required',
                'error_code' => 'name_is_required'
            ],
            [
                'key'        => 'name',
                'type'       => 'lengthMax',
                'value'      => 30,
                'error_code' => 'name_max_length_is_30'
            ],
            [
                'key'        => 'title',
                'type'       => 'required',
                'error_code' => 'title_is_required'
            ],
            [
                'key'        => 'title',
                'type'       => 'lengthMax',
                'value'      => 30,
                'error_code' => 'title_max_length_is_30'
            ],
            [
                'key'        => 'start_time',
                'type'       => 'required',
                'error_code' => 'start_time_is_required'
            ],
            [
                'key'        => 'end_time',
                'type'       => 'required',
                'error_code' => 'end_time_is_required'
            ],
            [
                'key'        => 'activity_desc',
                'type'       => 'required',
                'error_code' => 'activity_desc_is_required'
            ],
            [
                'key'        => 'activity_desc',
                'type'       => 'lengthMax',
                'value'      => 200,
                'error_code' => 'activity_desc_max_length_is_20'
            ],
            [
                'key'        => 'user_source',
                'type'       => 'required',
                'error_code' => 'user_source_is_required'
            ],
            [
                'key'        => 'user_source',
                'type'       => 'in',
                'value'      => [1, 2],
                'error_code' => 'user_source_value_error'
            ],
            [
                'key'        => 'app_id',
                'type'       => 'required',
                'error_code' => 'app_id_is_required'
            ],
            [
                'key'        => 'app_id',
                'type'       => 'in',
                'value'      => [1, 8],
                'error_code' => 'app_id_is_error'
            ],
            [
                'key'        => 'max_hit_type',
                'type'       => 'required',
                'error_code' => 'max_hit_is_required'
            ],
            [
                'key'        => 'max_hit_type',
                'type'       => 'in',
                'value'      => [1, 2],
                'error_code' => 'max_hit_type_value_error'
            ],
            [
                'key'        => 'max_hit',
                'type'       => 'min',
                'value'      => 1,
                'error_code' => 'max_hit_min_value_is_error'
            ],
            [
                'key'        => 'day_max_hit_type',
                'type'       => 'required',
                'error_code' => 'day_max_hit_is_required'
            ],

            [
                'key'        => 'day_max_hit_type',
                'type'       => 'in',
                'value'      => [1, 2],
                'error_code' => 'day_max_hit_type_value_error'
            ],
            [
                'key'        => 'day_max_hit',
                'type'       => 'min',
                'value'      => 1,
                'error_code' => 'day_max_hit_min_value_is_error'
            ],
            [
                'key'        => 'awards',
                'type'       => 'required',
                'error_code' => 'award_params_is_required'
            ],

        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $params['employee_uuid'] = $this->ci['employee']['uuid'];
        try {
            $res = LotteryAdminService::addOrUpdate($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 追加导流账户
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function appendImportUser(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key'        => 'op_activity_id',
                'type'       => 'required',
                'error_code' => 'op_activity_id_is_required'
            ],
            [
                'key'        => 'op_activity_id',
                'type'       => 'integer',
                'error_code' => 'op_activity_id_is_integer'
            ],
            [
                'key'        => 'op_activity_id',
                'type'       => 'min',
                'value'      => 1,
                'error_code' => 'op_activity_id_is_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $res = LotteryAdminService::appendImportUserData($params['op_activity_id'], $this->ci['employee']['uuid']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, [$res]);
    }

    /**
     * 活动列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key'        => 'name',
                'type'       => 'lengthMax',
                'value'      => 30,
                'error_code' => 'name_max_length_is_30'
            ],
            [
                'key'        => 'user_source',
                'type'       => 'in',
                'value'      => [1, 2],
                'error_code' => 'user_source_value_error'
            ],
            [
                'key'        => 'show_status',
                'type'       => 'in',
                'value'      => [1, 3, 4, 5, 6],
                'error_code' => 'show_status_value_error'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        list($page, $pageSize) = Util::formatPageCount($params);
        try {
            $res = LotteryAdminService::list($params, $page, $pageSize);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, [$res]);
    }

    /**
     * 活动详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function detail(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key'        => 'op_activity_id',
                'type'       => 'required',
                'error_code' => 'op_activity_id_is_required'
            ],
            [
                'key'        => 'op_activity_id',
                'type'       => 'integer',
                'error_code' => 'op_activity_id_is_integer'
            ],
            [
                'key'        => 'op_activity_id',
                'type'       => 'min',
                'value'      => 1,
                'error_code' => 'op_activity_id_is_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $res = LotteryAdminService::detail($params['op_activity_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, [$res]);
    }

    /**
     * 中奖信息记录列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function joinRecords(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key'        => 'op_activity_id',
                'type'       => 'required',
                'error_code' => 'op_activity_id_is_required'
            ],
            [
                'key'        => 'op_activity_id',
                'type'       => 'integer',
                'error_code' => 'op_activity_id_is_integer'
            ],
            [
                'key'        => 'op_activity_id',
                'type'       => 'min',
                'value'      => 1,
                'error_code' => 'op_activity_id_is_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $res = LotteryAdminService::joinRecords($params['op_activity_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, [$res]);
    }

}
