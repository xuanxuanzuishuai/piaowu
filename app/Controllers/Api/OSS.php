<?php

namespace App\Controllers\Api;

use App\Controllers\ControllerBase;
use App\Libs\AliOSS;
use App\Libs\DictConstants;
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
     * 阿里云OSS在上传文件完成的时候可以提供回调（Callback）给应用服务器。您只需要在发送给OSS的请求中携带相应的Callback参数，即能实现回调。
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function callback(Request $request, Response $response)
    {
        Util::unusedParam($request);

        $authorizationBase64 = '';
        $pubKeyUrlBase64 = '';

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authorizationBase64 = $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (isset($_SERVER['HTTP_X_OSS_PUB_KEY_URL'])) {
            $pubKeyUrlBase64 = $_SERVER['HTTP_X_OSS_PUB_KEY_URL'];
        }

        $alioss = new AliOSS();
        $requestUrl = $_SERVER['REQUEST_URI'];
        $ret = $alioss->uploadCallback($authorizationBase64, $pubKeyUrlBase64, $requestUrl);
        if ($ret){
            return $response->withJson(array("status"=>"Ok"), StatusCode::HTTP_OK);
        } else {
            return $response->withStatus(StatusCode::HTTP_FORBIDDEN);
        }
    }
}