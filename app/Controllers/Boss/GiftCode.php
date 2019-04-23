<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/22
 * Time: 4:27 PM
 */

namespace App\Controllers\Boss;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Models\GiftCodeModel;
use App\Services\GiftCodeService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;


class GiftCode extends ControllerBase
{
    public function add(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'num',
                'type' => 'required',
                'error_code' => 'num_is_required'
            ],
            [
                'key' => 'valid_num',
                'type' => 'required',
                'error_code' => 'valid_num_is_required'
            ],
            [
                'key' => 'valid_units',
                'type' => 'required',
                'error_code' => 'valid_units_is_required'
            ],
            [
                'key' => 'generate_channel',
                'type' => 'required',
                'error_code' => 'generate_channel_is_required'
            ],
            [
                'key' => 'buyer',
                'type' => 'required',
                'error_code' => 'buyer_is_required'
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        //当前操作人
        $employeeId = $this->ci['employee']['id'];

        //批量生成激活码
        $codeData = GiftCodeService::batchCreateCode(
            $params['num'],
            $params['valid_num'],
            $params['valid_units'],
            $params['generate_channel'],
            $params['buyer'],
            GiftCodeModel::CREATE_BY_MANUAL,
            $params['remarks'],
            $employeeId);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $codeData
        ], StatusCode::HTTP_OK);
    }

    //获取激活码列表
    public function list(Request $request, Response $response)
    {
        $params = $request->getParams();
        //获取激活码
        list($totalCount, $codeData) = GiftCodeService::batchGetCode($params);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'total_count' => $totalCount,
                'code_data' => $codeData
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 机构激活码列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function listForOrg(Request $request, Response $response)
    {
        $params = $request->getParams();
        global $orgId;
        $params['org_id'] = $orgId;

        list($totalCount, $codeData) = GiftCodeService::batchGetCode($params);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'total_count' => $totalCount,
                'code_data' => $codeData
            ]
        ], StatusCode::HTTP_OK);
    }

    //作废激活码
    public function abandon(Request $request, Response $response) {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'ids',
                'type' => 'required',
                'error_code' => 'ids_is_required'
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        //作废激活码
        $updatedCount = GiftCodeService::abandonCode($params);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'count' => $updatedCount,
        ], StatusCode::HTTP_OK);
    }
}