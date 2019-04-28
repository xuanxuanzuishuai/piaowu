<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/26
 * Time: 下午3:06
 */

namespace App\Controllers\Org;


use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Models\OrgAccountModel;
use App\Services\OrgAccountService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

/**
 * 机构账号
 * Class OrgAccount
 * @package App\Controllers\Org
 */
class OrgAccount extends ControllerBase
{
    public function list(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'page',
                'type'       => 'integer',
                'error_code' => 'page_is_integer'
            ],
            [
                'key'        => 'count',
                'type'       => 'integer',
                'error_code' => 'count_is_integer'
            ],
            [
                'key'        => 'account',
                'type'       => 'length',
                'value'      => 8,
                'error_code' => 'account_length_is_8'
            ],
            [
                'key'        => 'license_num',
                'type'       => 'integer',
                'error_code' => 'license_num_is_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        list($records, $total) = OrgAccountService::selectByPage($params['page'], $params['count'], $params);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'records'     => $records,
                'total_count' => $total
            ],
        ]);
    }

    public function listForOrg(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'page',
                'type'       => 'integer',
                'error_code' => 'page_is_integer'
            ],
            [
                'key'        => 'count',
                'type'       => 'integer',
                'error_code' => 'count_is_integer'
            ],
            [
                'key'        => 'account',
                'type'       => 'length',
                'value'      => 8,
                'error_code' => 'account_length_is_8'
            ],
            [
                'key'        => 'license_num',
                'type'       => 'integer',
                'error_code' => 'license_num_is_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        global $orgId;
        $params['org_id'] = $orgId;

        list($records, $total) = OrgAccountService::selectByPage($params['page'], $params['count'], $params);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'records'     => $records,
                'total_count' => $total
            ],
        ]);
    }

    public function detail(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'id',
                'type'       => 'required',
                'error_code' => 'id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $id = $params['id'];

        $record = OrgAccountModel::getById($id);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $record,
        ]);
    }

    public function modify(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'id',
                'type'       => 'required',
                'error_code' => 'id_is_required'
            ],
            [
                'key'        => 'status',
                'type'       => 'required',
                'error_code' => 'status_is_required'
            ],
            [
                'key'        => 'license_num',
                'type'       => 'required',
                'error_code' => 'id_is_required'
            ],
            [
                'key'        => 'status',
                'type'       => 'integer',
                'error_code' => 'integer_is_required'
            ],
            [
                'key'        => 'license_num',
                'type'       => 'integer',
                'error_code' => 'integer_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $data = [
            'status'      => $params['status'],
            'license_num' => $params['license_num'],
            'update_time' => time(),
        ];

        $id = $params['id'];

        $affectRows = OrgAccountModel::updateRecord($id, $data, false);

        if($affectRows == 0) {
            return $response->withJson(Valid::addErrors([], 'org_account', 'update_fail'));
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['affect_rows' => $affectRows]
        ]);
    }

    public function modifyPassword(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'org_account_id',
                'type'       => 'required',
                'error_code' => 'org_account_id_is_required'
            ],
            [
                'key'        => 'password',
                'type'       => 'required',
                'error_code' => 'password_is_required'
            ],
            [
                'key'        => 'password',
                'type'       => 'lengthMin',
                'value'      => 8,
                'error_code' => 'password_length_between_8_20'
            ],
            [
                'key'        => 'password',
                'type'       => 'lengthMax',
                'value'      => 20,
                'error_code' => 'password_length_between_8_20'
            ],
            [
                'key'        => 'password',
                'type'       => 'regex',
                'value'      => '/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{8,20}$/',
                'error_code' => 'password_must_has_number_and_alphabet'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $orgAccountId = $params['org_account_id'];
        global $orgId;

        $record = OrgAccountModel::getRecord([
            'id'     => $orgAccountId,
            'org_id' => $orgId,
        ], [], false);

        if(empty($record)) {
            return $response->withJson(Valid::addErrors([], 'org_account', 'org_account_not_exist'));
        }

        $account = $record['account'];
        $password = trim($params['password']);

        $affectRows = OrgAccountModel::updateRecord($orgAccountId, [
            'password'    => md5($account . $password),
            'update_time' => time(),
        ], false);

        if($affectRows == 0) {
            return $response->withJson(Valid::addErrors([], 'org_account', 'update_password_fail'));
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['affect_rows' => $affectRows]
        ]);
    }
}