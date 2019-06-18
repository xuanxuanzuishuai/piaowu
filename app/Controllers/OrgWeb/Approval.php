<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/6/18
 * Time: 11:56 AM
 */

namespace App\Controllers\OrgWeb;


use App\Controllers\ControllerBase;
use App\Libs\MysqlDB;
use App\Libs\Valid;
use App\Models\ApprovalLogModel;
use App\Models\ApprovalModel;
use App\Services\ApprovalConfigService;
use App\Services\ApprovalService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Approval extends ControllerBase
{
    public function addConfig(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'levels',
                'type' => 'required',
                'error_code' => 'levels_is_required'
            ],
            [
                'key' => 'roles',
                'type' => 'required',
                'error_code' => 'roles_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        if ($params['levels'] < 1 || $params['levels'] > ApprovalModel::MAX_LEVELS) {
            $result = Valid::addErrors([],'levels','levels_invalid');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        list($errorCode, $id) = ApprovalConfigService::addConfig($params['levels'],
            $params['roles'],
            $this->getEmployeeId());

        if (!empty($errorCode)) {
            $result = Valid::addErrors([],'roles',$errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'approval_config_id' => $id
            ],
        ], StatusCode::HTTP_OK);
    }

    public function discardConfig(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $errorCode = ApprovalConfigService::discardConfig($params['id']);

        if (!empty($errorCode)) {
            $result = Valid::addErrors([],'id',$errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [],
        ], StatusCode::HTTP_OK);
    }

    public function revoke(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $errorCode = ApprovalService::revoke($params['id'], $this->getEmployeeId());

        if (!empty($errorCode)) {
            $result = Valid::addErrors([],'id',$errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db->commit();

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [],
        ], StatusCode::HTTP_OK);
    }

    public function approve(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ],
            [
                'key' => 'op_type',
                'type' => 'in',
                'value' => [ApprovalLogModel::OP_APPROVE, ApprovalLogModel::OP_REJECT],
                'error_code' => 'op_type_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $errorCode = ApprovalService::approve($params['id'],
            $params['op_type'],
            $this->getEmployeeId(),
            $params['remark'] ?? '');

        if (!empty($errorCode)) {
            $result = Valid::addErrors([],'id',$errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db->commit();

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [],
        ], StatusCode::HTTP_OK);
    }
}