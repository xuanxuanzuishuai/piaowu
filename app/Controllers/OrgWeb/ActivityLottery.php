<?php
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
use App\Libs\Valid;
use App\Models\AgentModel;
use App\Services\ActivityCenterService;
use App\Services\AgentService;
use App\Services\CountingActivityService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use Slim\Http\Stream;

class ActivityLottery extends ControllerBase
{
    /**
     * 添加/编辑活动
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function addOrUpdate(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'name_is_required'
            ],
            [
                'key' => 'title',
                'type' => 'required',
                'error_code' => 'mobile_is_required'
            ],
            [
                'key' => 'app_id',
                'type' => 'required',
                'error_code' => 'app_id_is_required'
            ],
            [
                'key' => 'divide_type',
                'type' => 'required',
                'error_code' => 'divide_type_is_required'
            ],
            [
                'key' => 'agent_type',
                'type' => 'required',
                'error_code' => 'agent_type_is_required'
            ],
            [
                'key' => 'agent_type',
                'type' => 'in',
                'value' => array_keys(AgentModel::ONLINE_TYPE_MAP),
                'error_code' => 'agent_type_is_error'
            ],
            [
                'key' => 'country_code',
                'type' => 'required',
                'error_code' => 'country_code_is_required'
            ],
            [
                'key' => 'country_id',
                'type' => 'required',
                'error_code' => 'country_id_is_required'
            ],
            [
                'key' => 'division_model',
                'type' => 'required',
                'error_code' => 'division_model_is_required'
            ],
            [
                'key' => 'organization',
                'type' => 'lengthMax',
                'value' => 20,
                'error_code' => 'organization_max_length_is_20'
            ],
            [
                'key' => 'leads_allot_type',
                'type' => 'required',
                'error_code' => 'leads_allot_type_is_required'
            ],
            [
                'key' => 'leads_allot_type',
                'type' => 'in',
                'value' => [1, 2, 3],
                'error_code' => 'leads_allot_type_is_error'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $employeeId = self::getEmployeeId();
        try {
            AgentService::addAgent($params, $employeeId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

}
