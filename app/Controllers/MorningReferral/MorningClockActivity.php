<?php
/**
 * 清晨转介绍接口
 */

namespace App\Controllers\MorningReferral;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\MorningReferral\MorningClockActivityService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class MorningClockActivity extends ControllerBase
{
    /**
     * 5日打卡活动首页
     * @param Request  $request
     * @param Response $response
     * @return Response
     */
    public function getClockActivityIndex(Request $request, Response $response)
    {
        $data = MorningClockActivityService::getClockActivityIndex($this->ci['student_uuid']);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 5日打卡活动 - 打卡详情页
     * @param Request  $request
     * @param Response $response
     * @return Response
     */
    public function getClockActivityDayDetail(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'day',
                'type'       => 'required',
                'error_code' => 'day_is_required',
            ],
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = MorningClockActivityService::getClockActivityDayDetail($this->ci['student_uuid'], $params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 5日打卡活动 - 海报邀请语页面
     * @param Request  $request
     * @param Response $response
     * @return Response
     */
    public function getClockActivityShareWord(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'day',
                'type'       => 'required',
                'error_code' => 'day_is_required',
            ],
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = MorningClockActivityService::getClockActivityShareWord($this->ci['student_uuid'], $params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     *  5日打卡活动 - 上传截图
     * @param Request  $request
     * @param Response $response
     * @return Response
     */
    public function clockActivityUpload(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'day',
                'type'       => 'required',
                'error_code' => 'day_is_required',
            ],
            [
                'key'        => 'image_path',
                'type'       => 'required',
                'error_code' => 'image_path_is_required',
            ],
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = MorningClockActivityService::clockActivityUpload($this->ci['student_uuid'], $params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }
}
