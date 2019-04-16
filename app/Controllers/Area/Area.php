<?php
/**
 * Created by PhpStorm.
 * User: lijie
 * Date: 2018/10/26
 * Time: 11:51 AM
 */

namespace App\Routers\Area;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Services\AreaService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Area extends ControllerBase
{

    /**
     * 根据父级code获取列表
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function getByParentCode(Request $request, Response $response, $args)
    {

        $params = $request->getParams();
        $parent_code = $params['parent_code'];

        $result = AreaService::getAreaByParentCode($parent_code);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'area_list' => $result
            ]
        ], StatusCode::HTTP_OK);
    }


    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function getByCode(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'code',
                'type' => 'required',
                'error_code' => 'area_code_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $result = AreaService::getByCode($params['code']);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'area_info' => $result
            ]
        ], StatusCode::HTTP_OK);
    }
}