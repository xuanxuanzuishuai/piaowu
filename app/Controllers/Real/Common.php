<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/10/15
 * Time: 15:41
 */

namespace App\Controllers\Real;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Services\CommonServiceForApp;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * 真人业务线学生端各端共用接口控制器文件
 * Class StudentActivity
 * @package App\Routers
 */
class Common extends ControllerBase
{

    /**
     * 国际区号列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getCountryCode(/** @noinspection PhpUnusedParameterInspection */ Request $request, Response $response)
    {
        $countryCode = CommonServiceForApp::getCountryCodeOrderByHot();
        return HttpHelper::buildResponse($response, $countryCode);
    }

}
