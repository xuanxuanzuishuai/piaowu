<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/03/02
 * Time: 6:32 PM
 */

namespace App\Controllers\StudentWeb;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Services\CollectionService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Collection extends ControllerBase
{
    /**
     * 获取学生所属班级集合的信息
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function getCollectionData(Request $request, Response $response, $args)
    {
        //接收数据
        $rules = [
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        //获取分配的集合信息
        $data = CollectionService::getCollectionByUserUUId($params['uuid']);
        //返回数据
        return $response->withJson([
            'code' => 0,
            'data' => $data
        ], StatusCode::HTTP_OK);
    }
}