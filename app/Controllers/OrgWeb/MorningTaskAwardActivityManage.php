<?php
/**
 * 清晨任务奖励管理
 * author: qingfeng.lian
 * date: 2022/10/14
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\MorningReferral\MorningTaskAwardActivityManageService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class MorningTaskAwardActivityManage extends ControllerBase
{

    /**
     * 红包奖励列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function redPackList(Request $request, Response $response)
    {
        $params = $request->getParams();
        $params['employee_id'] = self::getEmployeeId();
        try {
            list($params['page'], $params['count']) = Util::formatPageCount($params);
            $data = MorningTaskAwardActivityManageService::redPackList($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 手动发放红包
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function redPackUpdateStatus(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'poster_ids',
                'type'       => 'required',
                'error_code' => 'poster_ids_is_required',
            ],
        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $params['employee_id'] = self::getEmployeeId();
        try {
            MorningTaskAwardActivityManageService::redPackUpdateStatus($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }
}