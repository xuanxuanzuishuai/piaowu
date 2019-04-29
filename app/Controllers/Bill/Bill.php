<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/28
 * Time: 下午6:47
 */

namespace App\Controllers\Bill;


use App\Controllers\ControllerBase;
use App\Models\BillModel;
use App\Services\BillService;
use App\Services\StudentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Libs\Valid;
/**
 * 订单
 * Class Bill
 * @package App\Controllers\Bill\
 */
class Bill extends ControllerBase
{
    public function list(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'page',
                'type'       => 'integer',
                'error_code' => 'page_must_be_integer',
            ],
            [
                'key'        => 'count',
                'type'       => 'integer',
                'error_code' => 'count_must_be_integer',
            ],
            [
                'key'        => 'pay_status',
                'type'       => 'integer',
                'error_code' => 'pay_channel_must_be_integer',
            ],
            [
                'key'        => 'pay_channel',
                'type'       => 'integer',
                'error_code' => 'pay_channel_must_be_integer',
            ],
            [
                'key'        => 'student_id',
                'type'       => 'integer',
                'error_code' => 'student_id_must_be_integer',
            ],
            [
                'key'        => 'source',
                'type'       => 'integer',
                'error_code' => 'source_must_be_integer',
            ],
            [
                'key'        => 'start_create_time',
                'type'       => 'integer',
                'error_code' => 'start_create_time_must_be_integer',
            ],
            [
                'key'        => 'end_create_time',
                'type'       => 'integer',
                'error_code' => 'end_create_time_must_be_integer',
            ],
            [
                'key'        => 'org_id',
                'type'       => 'integer',
                'error_code' => 'org_id_must_be_integer',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        list($records, $total) = BillService::selectByPage(
            $params['org_id'], $params['page'], $params['count'], $params
        );

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'records'     => $records,
                'total_count' => $total,
            ],
        ]);
    }

    public function listForOrg(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'page',
                'type'       => 'integer',
                'error_code' => 'page_must_be_integer',
            ],
            [
                'key'        => 'count',
                'type'       => 'integer',
                'error_code' => 'count_must_be_integer',
            ],
            [
                'key'        => 'pay_status',
                'type'       => 'integer',
                'error_code' => 'pay_channel_must_be_integer',
            ],
            [
                'key'        => 'pay_channel',
                'type'       => 'integer',
                'error_code' => 'page_must_be_integer',
            ],
            [
                'key'        => 'student_id',
                'type'       => 'integer',
                'error_code' => 'student_id_must_be_integer',
            ],
            [
                'key'        => 'source',
                'type'       => 'integer',
                'error_code' => 'source_must_be_integer',
            ],
            [
                'key'        => 'start_create_time',
                'type'       => 'integer',
                'error_code' => 'start_create_time_must_be_integer',
            ],
            [
                'key'        => 'end_create_time',
                'type'       => 'integer',
                'error_code' => 'end_create_time_must_be_integer',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        global $orgId;

        list($records, $total) = BillService::selectByPage(
            $orgId, $params['page'], $params['count'], $params
        );

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'records'     => $records,
                'total_count' => $total,
            ],
        ]);
    }

    public function detail(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'id',
                'type'       => 'required',
                'error_code' => 'id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $id = $params['id'];
        global $orgId;

        $record = BillModel::getBillByOrgAndId($orgId, $id);
        if(!empty($record)) {
            $record['amount'] /= 100;
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $record,
        ]);
    }

    public function add(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'student_id',
                'type'       => 'required',
                'error_code' => 'student_id_is_required',
            ],
            [
                'key'        => 'pay_status',
                'type'       => 'required',
                'error_code' => 'pay_status_is_required',
            ],
            [
                'key'        => 'amount',
                'type'       => 'required',
                'error_code' => 'amount_is_required',
            ],
            [
                'key'        => 'amount',
                'type'       => 'min',
                'value'      => 0,
                'error_code' => 'amount_is_egt_0',
            ],
            [
                'key'        => 'pay_channel',
                'type'       => 'required',
                'error_code' => 'pay_channel_is_required',
            ],
            [
                'key'        => 'source',
                'type'       => 'required',
                'error_code' => 'source_is_required',
            ],
            [
                'key'        => 'source',
                'type'       => 'in',
                'value'      => [1, 2],
                'error_code' => 'source_must_in_1_2',
            ],
            [
                'key'        => 'remark',
                'type'       => 'lengthMax',
                'value'      => 1024,
                'error_code' => 'remark_must_elt_1024',
            ],
            [
                'key'        => 'trade_no',
                'type'       => 'lengthMax',
                'value'      => 50,
                'error_code' => 'trade_no_must_elt_50',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        global $orgId;

        $studentId = $params['student_id'];

        $student = StudentService::getOrgStudent($orgId, $studentId);
        if(empty($student)) {
            return $response->withJson(Valid::addErrors([], 'bill', 'student_not_exist'));
        }

        $now = time();

        $columns = [
            'student_id', 'pay_status', 'trade_no',
            'pay_channel', 'source','remark',
        ];

        $data = [
            'create_time' => $now,
            'end_time'    => $now,
            'operator_id' => $this->getEmployeeId(),
            'org_id'      => $orgId,
            'amount'      => $params['amount'] * 100,
        ];

        foreach($columns as $key) {
            $data[$key] = $params[$key];
        }

        $lastId = BillModel::insertRecord($data, false);
        if(is_null($lastId)) {
            return $response->withJson(Valid::addErrors([], 'bill', 'save_bill_fail'));
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['last_id' => $lastId],
        ]);
    }

    public function modify(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'id',
                'type'       => 'required',
                'error_code' => 'id_is_required',
            ],
            [
                'key'        => 'student_id',
                'type'       => 'required',
                'error_code' => 'student_id_is_required',
            ],
            [
                'key'        => 'pay_status',
                'type'       => 'required',
                'error_code' => 'pay_status_is_required',
            ],
            [
                'key'        => 'amount',
                'type'       => 'required',
                'error_code' => 'amount_is_required',
            ],
            [
                'key'        => 'amount',
                'type'       => 'min',
                'value'      => 0,
                'error_code' => 'amount_is_egt_0',
            ],
            [
                'key'        => 'pay_channel',
                'type'       => 'required',
                'error_code' => 'pay_channel_is_required',
            ],
            [
                'key'        => 'source',
                'type'       => 'required',
                'error_code' => 'source_is_required',
            ],
            [
                'key'        => 'source',
                'type'       => 'in',
                'value'      => [1, 2],
                'error_code' => 'source_must_in_1_2',
            ],
            [
                'key'        => 'remark',
                'type'       => 'lengthMax',
                'value'      => 1024,
                'error_code' => 'remark_must_elt_1024',
            ],
            [
                'key'        => 'trade_no',
                'type'       => 'lengthMax',
                'value'      => 50,
                'error_code' => 'trade_no_must_elt_50',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        global $orgId;

        $studentId = $params['student_id'];

        $student = StudentService::getOrgStudent($orgId, $studentId);
        if(empty($student)) {
            return $response->withJson(Valid::addErrors([], 'bill', 'student_not_exist'));
        }

        $columns = [
            'student_id', 'pay_status', 'remark',
            'trade_no', 'pay_channel', 'source',
        ];
        $data = [
            'update_time' => time(),
            'operator_id' => $this->getEmployeeId(),
            'amount'      => $params['amount'] * 100,
        ];
        foreach($columns as $key) {
            $data[$key] = $params[$key];
        }

        $id = $params['id'];

        $affectRows = BillModel::updateBill($id, $orgId, $data);
        if($affectRows == 0) {
            return $response->withJson(Valid::addErrors([], 'bill', 'update_bill_fail'));
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['affect_rows' => $affectRows],
        ]);
    }
}