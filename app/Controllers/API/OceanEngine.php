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

class OceanEngine extends ControllerBase
{
    public function track(Request $request, Response $response)
    {
        $params = $request->getParams();
        SimpleLogger::debug("OceanEngine::track", [$params]);

        return HttpHelper::buildResponse($response, $params);
    }
}