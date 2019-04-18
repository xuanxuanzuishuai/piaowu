<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/17
 * Time: 下午6:04
 */

namespace App\Controllers\Org;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Models\OrganizationModel;
use App\Services\OrganizationService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Org extends ControllerBase
{
    /**
     * 添加或更新机构
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Org|Response
     */
    public function addOrUpdate(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'id',
                'type'       => 'integer',
                'error_code' => 'id_must_be_integer'
            ],
            [
                'key'        => 'name',
                'type'       => 'required',
                'error_code' => 'name_is_required'
            ],
            [
                'key'        => 'name',
                'type'       => 'lengthMax',
                'value'      => 128,
                'error_code' => 'name_max_must_elt_128'
            ],
            [
                'key'        => 'remark',
                'type'       => 'lengthMax',
                'value'      => 1024,
                'error_code' => 'remark_must_elt_1024'
            ],
            [
                'key'        => 'province_code',
                'type'       => 'required',
                'error_code' => 'province_code_is_required'
            ],
            [
                'key'        => 'province_code',
                'type'       => 'integer',
                'error_code' => 'province_code_must_be_integer'
            ],
            [
                'key'        => 'city_code',
                'type'       => 'required',
                'error_code' => 'city_code_is_required'
            ],
            [
                'key'        => 'city_code',
                'type'       => 'integer',
                'error_code' => 'city_code_must_be_integer'
            ],
            [
                'key'        => 'district_code',
                'type'       => 'integer',
                'error_code' => 'district_code_must_be_integer'
            ],
            [
                'key'        => 'address',
                'type'       => 'required',
                'error_code' => 'address_is_required'
            ],
            [
                'key'        => 'address',
                'type'       => 'lengthMax',
                'value'      => 128,
                'error_code' => 'address_must_elt_128'
            ],
            [
                'key'        => 'zip_code',
                'type'       => 'integer',
                'error_code' => 'zip_code_must_be_integer'
            ],
            [
                'key'        => 'register_channel',
                'type'       => 'lengthMax',
                'value'      => 128,
                'error_code' => 'register_channel_must_elt_128'
            ],
            [
                'key'        => 'parent_id',
                'type'       => 'integer',
                'error_code' => 'parent_id_must_be_integer'
            ],
            [
                'key'        => 'start_time',
                'type'       => 'required',
                'error_code' => 'start_time_is_required'
            ],
            [
                'key'        => 'start_time',
                'type'       => 'integer',
                'error_code' => 'start_time_must_be_integer'
            ],
            [
                'key'        => 'start_time',
                'type'       => 'lengthMax',
                'value'      => 10,
                'error_code' => 'start_time_length_is_10'
            ],
            [
                'key'        => 'end_time',
                'type'       => 'required',
                'error_code' => 'end_time_is_required'
            ],
            [
                'key'        => 'end_time',
                'type'       => 'integer',
                'error_code' => 'end_time_must_be_integer'
            ],
            [
                'key'        => 'end_time',
                'type'       => 'lengthMax',
                'value'      => 10,
                'error_code' => 'end_time_length_is_10'
            ],
            [
                'key'        => 'status',
                'type'       => 'required',
                'error_code' => 'status_is_required'
            ],
            [
                'key'        => 'status',
                'type'       => 'integer',
                'error_code' => 'status_must_be_integer'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $now = time();
        $params['update_time'] = $now;
        $params['operator_id'] = $this->getEmployeeId();

        if(isset($params['id'])) {
            $id = $params['id'];
            unset($params['id']);
            $affectRows = OrganizationService::updateById($id, $params);
            if($affectRows == 0) {
                return $this->fail($response, 'org','update_fail');
            }

            return $this->success($response, ['last_id' => $id]);
        } else {
            $params['create_time'] = $now;
            $params['status']      = OrganizationModel::STATUS_NORMAL;

            $lastId = OrganizationService::save($params);

            if(empty($lastId)) {
                return $response->withJson(Valid::addErrors([],'org','save_org_fail'));
            }

            return $this->success($response, ['last_id' => $lastId]);
        }
    }

    /**
     * 机构列表
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function list(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'page',
                'type'       => 'required',
                'error_code' => 'page_is_required'
            ],
            [
                'key'        => 'count',
                'type'       => 'required',
                'error_code' => 'count_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        list($records, $total) = OrganizationService::selectOrgList($params['page'], $params['count'], $params);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['records' => $records,'total_count' => $total]
        ]);
    }
}