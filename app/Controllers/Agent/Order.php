<?php


namespace App\Controllers\Agent;


use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\AgentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Order
{
    /**
     * 代理推广订单列表
     * @param Request $request
     * @param Response $response
     */
    public function list(Request $request, Response $response)
    {
        //接受校验参数
        $rules = [
            [
                'key' => 'type',
                'type' => 'required',
                'error_code' => 'type_is_required'
            ],
            [
                'key' => 'page',
                'type' => 'integer',
                'error_code' => 'page_is_integer'
            ],
            [
                'key' => 'limit',
                'type' => 'integer',
                'error_code' => 'limit_is_integer'
            ]
        ];
        $userInfo = $this->ci['user_info'] ?? [];
        $agentId = $userInfo['user_id'] ?? 0;
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            list($page, $limit) = Util::formatPageCount($params);
            $bindOrderData = AgentService::getPopularizeOrderList($agentId, $params['type'], $page, $limit);
            //返回数据
            return HttpHelper::buildResponse($response, $bindOrderData);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
    }
}