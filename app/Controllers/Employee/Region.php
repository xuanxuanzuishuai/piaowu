<?php

namespace App\Controllers\Employee;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Models\AreaRegionModel;
use App\Models\RegionBelongManageModel;
use App\Models\RegionProvinceRelationModel;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;


class Region extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function regionList(Request $request, Response $response, $args)
    {
        try {
            $info = AreaRegionModel::getRecords(['id[>]' => 0]);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'region_list' => $info,
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function regionRelateProvince(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'region_id',
                'type' => 'required',
                'error_code' => 'region_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $info = RegionProvinceRelationModel::getRelateProvince($params['region_id']);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'region_relate_province' => $info,
        ], StatusCode::HTTP_OK);
    }
}