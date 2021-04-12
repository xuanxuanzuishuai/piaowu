<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/2/1
 * Time: 10:41
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\PackageService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Package extends ControllerBase
{
    /**
     * 课包搜索
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function search(Request $request, Response $response)
    {
        $params = $request->getParams();
        $data = PackageService::packageSearch($params);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取新产品包
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getNewPackage(Request $request, Response $response)
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
        $params = $request->getParams();
        $data = PackageService::getPackageBySubType($params['sub_type']);
        return HttpHelper::buildResponse($response, $data);
    }
}