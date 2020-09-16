<?php
/**
 * Created by PhpStorm.
 * User: theone
 * Date: 2020/3/11
 * Time: 6:19 PM
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Services\DictService;
use App\Libs\Valid;
use App\Services\ErpServiceV1\ErpPackageV1Service;
use App\Services\PackageService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Package extends ControllerBase
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
        $packageIDUpdateRes = DictService::updateValue(DictConstants::PACKAGE_CONFIG['type'],'package_id',trim($params['package_id']));
        $plusPackageIDUpdateRes = DictService::updateValue(DictConstants::PACKAGE_CONFIG['type'],'plus_package_id',trim($params['plus_package_id']));
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
        $type = isset($params['type']) ? $params['type'] : DictConstants::PACKAGE_CONFIG['type'];
        //获取数据
        $list = DictService::getTypeMap($type);
        return $response->withJson([
            'code' => 0,
            'data' => ['list' => $list]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 产品包列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        $params = $request->getParams();

        list($records, $count) = PackageService::packageList($params);

        return HttpHelper::buildResponse($response, [
            'list' => $records,
            'total_count' => $count
        ]);
    }

    /**
     * 编辑产品包扩展信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function edit(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'package_id',
                'type' => 'required',
                'error_code' => 'package_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $operatorId = $this->getEmployeeId();

        try {
            PackageService::packageEdit($params, $operatorId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 获取新产品包信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function packageListV1(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'sub_type',
                'type' => 'required',
                'error_code' => 'sub_type_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $records = ErpPackageV1Service::getPackages($params['sub_type']);

        return HttpHelper::buildResponse($response, $records);
    }
}