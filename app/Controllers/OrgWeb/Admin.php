<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/9/18
 * Time: 10:50 AM
 */

namespace App\Controllers\OrgWeb;

use App\Libs\HttpHelper;
use App\Libs\RedisDB;
use App\Libs\Valid;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Admin
{
    public function fakeSMSCode(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'mobile_is_required'
            ],
            [
                'key' => 'code',
                'type' => 'required',
                'error_code' => 'code_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $cacheKey = 'v_code_' . $params['mobile'];
        $redis = RedisDB::getConn();
        $redis->setex($cacheKey, 300, $params['code']);

        return HttpHelper::buildResponse($response, []);
    }
}