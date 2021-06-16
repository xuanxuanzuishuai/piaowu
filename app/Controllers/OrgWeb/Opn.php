<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/22
 * Time: 10:41
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\ErpOpnService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Opn extends ControllerBase
{

    /**
     * 曲谱教材下拉框搜索
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function dropDownSearch(Request $request, Response $response)
    {
        $params = $request->getParams();
        $list = ErpOpnService::opnDropDownSearch($params);
        return HttpHelper::buildResponse($response, $list);
    }
}