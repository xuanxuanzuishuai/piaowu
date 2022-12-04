<?php

namespace App\Controllers\Api;

use App\Controllers\ControllerBase;
use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\HttpHelper;
use App\Libs\Util;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class OSS extends ControllerBase
{
    public function signature(Request $request, Response $response)
    {
        Util::unusedParam($request);

        $ossConfig = DictConstants::getSet(DictConstants::ALI_OSS_CONFIG);

        $dir = $request->getParam('type');

        $alioss = new AliOSS();
        $ret = $alioss->getSignature($ossConfig['access_key_id'],
            $ossConfig['access_key_secret'],
            $ossConfig['bucket'],
            $ossConfig['endpoint'],
            $ossConfig['callback_url'],
            $dir,
            $ossConfig['expire'],
            $ossConfig['max_file_size']);

        return $response->withJson($ret, StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function callback(Request $request, Response $response)
    {
        return HttpHelper::buildResponse($response, []);
    }
}