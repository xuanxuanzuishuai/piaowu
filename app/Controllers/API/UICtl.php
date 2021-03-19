<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2018/11/8
 * Time: 下午4:47
 */

namespace App\Controllers\API;

use App\Controllers\ControllerBase;
use App\Libs\DictConstants;
use App\Libs\Valid;
use App\Services\DictService;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

/** @var App $app */
/**
 * 页面下拉菜单取值
 */
class UICtl extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function dropdown(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'dp_types',
                'type' => 'required',
                'error_code' => 'dp_type_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $keys = explode(",", $params['dp_types']);

        // service 是 erp 读取erp.erp_dict表， 默认读取op.dict表
        if ($params['service'] == 'erp') {
            $filterCode = !empty($params['filter_code']) ? explode(',',$params['filter_code']) : [];
            $getCode = !empty($params['code']) ? explode(',',$params['code']) : [];
            $result = DictConstants::getErpDictArr($keys, $getCode, $filterCode);
        }else {
            $result = DictService::getListsByTypes($keys);
        }

        return $response->withJson($result, StatusCode::HTTP_OK);

    }
}