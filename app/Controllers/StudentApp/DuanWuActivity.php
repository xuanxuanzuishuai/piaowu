<?php
/**
 * 周周有奖 - 活动管理
 */

namespace App\Controllers\StudentApp;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Services\ActivityDuanWuService;
use Slim\Http\Request;
use Slim\Http\Response;

class DuanWuActivity extends ControllerBase
{
    /**
     * 端午节活动详情接口
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function activityInfo(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $params['user_info'] = $this->ci['user_info'];
            $data = ActivityDuanWuService::activityInfo($params);
        } catch (\Exception $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }
    
    /**
     * 端午节活动推荐列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function refereeList(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $params['user_info'] = $this->ci['user_info'];
            list($page, $count) = Util::formatPageCount($params);
            $data = ActivityDuanWuService::refereeList($params, $page, $count);
        } catch (\Exception $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }
}
