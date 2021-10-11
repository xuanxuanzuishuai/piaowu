<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/18
 * Time: 8:51 PM
 */

namespace App\Controllers\StudentApp;


use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Models\CopyManageModel;
use App\Services\CopyManageService;
use Slim\Http\Request;
use Slim\Http\Response;

class GoldLeafShop extends ControllerBase
{

    /**
     * 获取金叶子商城规则说明文案
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function ruleDesc(/** @noinspection PhpUnusedParameterInspection */ Request $request, Response $response)
    {
        $data = CopyManageService::getRuleDesc(CopyManageModel::RULE_DESC_TYPE_DSS_GOLD_LEAF);
        return HttpHelper::buildResponse($response, $data);
    }
}