<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/7
 * Time: 7:08 PM
 */

namespace App\Controllers\API;

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

        $alioss = new AliOSS();
        $ret = $alioss->getSignature($ossConfig['access_key_id'],
            $ossConfig['access_key_secret'],
            $ossConfig['host'],
            $ossConfig['callback_url'],
            $ossConfig['img_dir'],
            $ossConfig['expire'],
            $ossConfig['max_file_size']);

        return $response->withJson($ret, StatusCode::HTTP_OK);
    }

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