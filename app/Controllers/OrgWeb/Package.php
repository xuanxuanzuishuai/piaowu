<?php
/**
 * Created by PhpStorm.
 * User: theone
 * Date: 2020/3/11
 * Time: 6:19 PM
 */

namespace App\Controllers\OrgWeb;

use App\Libs\DictConstants;
use App\Services\DictService;
use App\Libs\Valid;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Package
{
    /**
     * 设置课包dict配置数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function packageDictEdit(Request $request, Response $response)
    {

        $rules = [
            [
                'key' => 'package_id',
                'type' => 'lengthmax',
                'value' => 255,
                'error_code' => 'package_id_length_over'
            ],
            [
                'key' => 'plus_package_id',
                'type' => 'lengthmax',
                'value' => 255,
                'error_code' => 'package_id_length_over'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        //修改数据
        $packageIDUpdateRes = DictService::updateValue(DictConstants::WEB_STUDENT_CONFIG['type'],'package_id',trim($params['package_id']));
        $plusPackageIDUpdateRes = DictService::updateValue(DictConstants::WEB_STUDENT_CONFIG['type'],'plus_package_id',trim($params['plus_package_id']));
        if(empty($plusPackageIDUpdateRes) && empty($packageIDUpdateRes)){
            return $response->withJson(Valid::addErrors([], 'package_dict', 'update_package_dict_fail'));
        }
        return $response->withJson([
            'code' => 0,
            'data' => []
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取课包dict配置数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function packageDictDetail(Request $request, Response $response)
    {
        //接收参数
        $params = $request->getParams();
        $type = isset($params['type']) ? $params['type'] : DictConstants::WEB_STUDENT_CONFIG['type'];
        //获取数据
        $list = DictService::getTypeMap($type);
        return $response->withJson([
            'code' => 0,
            'data' => ['list' => $list]
        ], StatusCode::HTTP_OK);
    }
}