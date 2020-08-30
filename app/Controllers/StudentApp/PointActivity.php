<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/07/06
 * Time: 上午10:47
 */

namespace App\Controllers\StudentApp;

use App\Libs\Util;
use App\Libs\Valid;
use App\Services\MedalService;
use App\Services\PointActivityService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\Exceptions\RunTimeException;
use Slim\Http\StatusCode;

class PointActivity extends ControllerBase
{
    /**
     * 获取用户可参与积分活动列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function activityList(Request $request, Response $response)
    {
        $params = $request->getParams();
        try {
            if (empty($this->ci['version'])) {
                throw new RunTimeException(['app_version_is_required']);
            }
            $records = PointActivityService::getPointActivityListInfo($params['activity_type'], $this->ci['student']['id'], $this->ci['version']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $records);
    }

    /**
     * 任务完成上报数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function report(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'activity_type',
                'type' => 'required',
                'error_code' => 'activity_type_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            if (empty($this->ci['version'])) {
                throw new RunTimeException(['app_version_is_required']);
            }
            $records = PointActivityService::reportRecord($params['activity_type'], $this->ci['student']['id'], ['app_version' => $this->ci['version']]);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $records);
    }

    /**
     * 获取学生积分明细列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function pointsDetail(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($page, $count) = Util::formatPageCount($params);
        $data = PointActivityService::pointsDetail($this->ci['student']['id'], $page, $count);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 异步获取尚未弹出的奖章
     */
    public function needAlert(Request $request, Response $response)
    {
        $data = MedalService::getNeedAlertMedal($this->ci['student']['id']);
        return HttpHelper::buildResponse($response, $data);
    }
}