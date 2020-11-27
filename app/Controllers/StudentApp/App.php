<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/18
 * Time: 8:51 PM
 */

namespace App\Controllers\StudentApp;


use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Services\CommonServiceForApp;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class App extends ControllerBase
{
    /**
     * 国家代码列表(缓存)
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function countryCode(/** @noinspection PhpUnusedParameterInspection */ Request $request, Response $response)
    {
        $countryCode = CommonServiceForApp::getCountryCode();
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $countryCode,
        ], StatusCode::HTTP_OK);
    }
}