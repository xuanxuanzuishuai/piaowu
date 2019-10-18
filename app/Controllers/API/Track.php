<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/10/11
 * Time: 7:08 PM
 */

namespace App\Controllers\API;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\SimpleLogger;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Track extends ControllerBase
{
    public function adEventOceanEngine(Request $request, Response $response)
    {
        $params = $request->getParams();
        SimpleLogger::debug("OceanEngine::track", [$params]);

        $ret = ['status' => 0];
        return $response->withJson($ret, StatusCode::HTTP_OK);
    }

    public function adEventGdt(Request $request, Response $response)
    {
        $params = $request->getParams();
        SimpleLogger::debug("OceanEngine::track", [$params]);

        $ret = ['ret' => 0, 'msg' => 'OK'];
        return $response->withJson($ret, StatusCode::HTTP_OK);
    }
}