<?php
/**
 * 金叶子积分商城金叶子相关接口
 */

namespace App\Controllers\StudentWX;


use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\CopyManageModel;
use App\Services\CopyManageService;
use App\Services\ErpUserEventTaskAwardGoldLeafService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class GoldLeafShop extends ControllerBase
{
    public function goldLeafList(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required',
            ],
            [
                'key' => 'page',
                'type' => 'integer',
                'error_code' => 'page_is_integer'
            ],
            [
                'key' => 'count',
                'type' => 'integer',
                'error_code' => 'count_is_integer'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            list($page, $limit) = Util::formatPageCount($params);
            $res = ErpUserEventTaskAwardGoldLeafService::getWaitingGoldLeafList($params, $page, $limit);
        } catch (RunTimeException $e) {
            SimpleLogger::info("Erp::integralExchangeRedPack error", ['params' => $params, 'err' => $e->getData()]);
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $res);
    }

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