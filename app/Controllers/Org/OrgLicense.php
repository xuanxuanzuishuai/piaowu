<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/28
 * Time: 11:09 AM
 */

namespace App\Controllers\Org;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Valid;
use App\Services\OrgLicenseService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class OrgLicense extends ControllerBase
{
    public function create(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'org_id',
                'type'       => 'required',
                'error_code' => 'org_id_is_required'
            ],
            [
                'key'        => 'num',
                'type'       => 'integer',
                'error_code' => 'license_num_must_be_integer'
            ],
            [
                'key'        => 'duration',
                'type'       => 'integer',
                'error_code' => 'license_duration_must_be_integer'
            ],
            [
                'key'        => 'unit',
                'type'       => 'in',
                'value'      => [Constants::UNIT_DAY, Constants::UNIT_MONTH, Constants::UNIT_YEAR],
                'error_code' => 'invalid_license_duration_unit'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $orgId = $params['org_id'];
        $num = $params['num'] ?? 1;
        $duration = $params['duration'] ?? 1;
        $unit = $params['unit'] ?? Constants::UNIT_YEAR;
        $ret = OrgLicenseService::create($orgId, $num, $duration, $unit, $this->getEmployeeId());

        if (empty($ret)) {
            $result = Valid::addErrors([], 'org_id', 'license_create_failure');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [],
        ]);
    }

    public function disable(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'license_id',
                'type'       => 'required',
                'error_code' => 'license_id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $ret = OrgLicenseService::disable($params['license_id'], $this->getEmployeeId());
        if (empty($ret)) {
            $result = Valid::addErrors([], 'license_id', 'license_disable_failure');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [],
        ]);
    }

    public function active(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'license_id',
                'type'       => 'required',
                'error_code' => 'license_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $ret = OrgLicenseService::active($params['license_id'], $this->getEmployeeOrgId(), $this->getEmployeeId());
        if (empty($ret)) {
            $result = Valid::addErrors([], 'license_id', 'license_active_failure');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }


        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [],
        ]);
    }
}