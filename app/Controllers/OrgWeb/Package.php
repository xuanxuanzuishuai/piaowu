<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/2/1
 * Time: 10:41
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\AgentService;
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
}