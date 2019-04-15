<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2018/11/8
 * Time: 下午4:47
 */

namespace App\Controllers\API;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Services\AppService;
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

        // 如果存在应用业务，作单独处理
        $appType = [];
        $appTypeName = 'app_type';
        if (in_array($appTypeName, $keys)) {
            $keys = array_diff($keys, [$appTypeName]);
            // 应用类型单独处理，不从dict中获取（先查询缓存是否存在，没有则从数据库取）
            $appType = AppService::getAppTypeList($appTypeName);
        }

        $result = DictService::getListsByTypes($keys);

        // 存在应用业务
        if (!empty($appType)) {
            $result = array_merge($result, $appType);
        }

        return $response->withJson($result, StatusCode::HTTP_OK);

    }
}