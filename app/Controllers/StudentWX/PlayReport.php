<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/4/3
 * Time: 3:45 PM
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Models\StudentModel;
use App\Services\AIPlayReportService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class PlayReport extends ControllerBase
{

    /**
     * 练琴日报
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function dayReport(Request $request, Response $response)
    {
        $params = $request->getParams();
        $studentId = $this->ci['user_info']['user_id'];
        try {
            $result = AIPlayReportService::getDayReport($studentId, $params["date"]);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $result);
    }



    /**
     * 练琴日报(分享)
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sharedDayReport(Request $request, Response $response)
    {
        $params = $request->getParams();

        try {
            $result = AIPlayReportService::getSharedDayReport($params["share_token"]);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $result);
    }

    /**
     * 日报点赞
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function dayReportFabulous(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'wx_code',
                'type' => 'required',
                'error_code' => 'wx_code_is_required'
            ],
            [
                'key' => 'share_token',
                'type' => 'required',
                'error_code' => 'share_token_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $openId = $this->ci["open_id"];
        if (empty($openId)) {
            return $response->withJson(Valid::addAppErrors([], 'can_not_obtain_open_id'), StatusCode::HTTP_OK);
        }

        try {
             AIPlayReportService::dayReportFabulous($params["share_token"], $openId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, []);

    }

    /**
     * 日报点赞（自己给自己点赞）
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function dayReportOneSelfFabulous(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'date',
                'type' => 'required',
                'error_code' => 'date_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $openId = $this->ci["open_id"];
        if (empty($openId)) {
            return $response->withJson(Valid::addAppErrors([], 'can_not_obtain_open_id'), StatusCode::HTTP_OK);
        }

        $studentId = $this->ci['user_info']['user_id'];
        try {
            AIPlayReportService::dayReportOneSelfFabulous($openId, $studentId, $params['date']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, []);

    }
    /**
     * 练琴日历
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function playCalendar(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'month',
                'type' => 'required',
                'error_code' => 'month_is_required'
            ],
            [
                'key' => 'year',
                'type' => 'required',
                'error_code' => 'year_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $studentId = $this->ci['user_info']['user_id'];
        $student = StudentModel::getById($studentId);

        if (empty($student)) {
            HttpHelper::buildResponse($response, []);
        }

        $calendar = AIPlayReportService::getPlayCalendar($studentId, $params["year"], $params["month"]);

        return HttpHelper::buildResponse($response, ['calendar' => $calendar]);
    }

    /**
     * 单课测评成绩单
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function lessonTestReport(Request $request, Response $response)
    {
        $params = $request->getParams();

        $studentId = $this->ci['user_info']['user_id'];

        try {
            $result = AIPlayReportService::getLessonTestReport($studentId, $params["lesson_id"], $params["date"]);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $result);
    }

    /**
     * 单课测评成绩单(分享)
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sharedLessonTestReport(Request $request, Response $response)
    {
        $params = $request->getParams();

        try {
            $result = AIPlayReportService::getSharedLessonTestReport($params["share_token"], $params["lesson_id"]);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $result);
    }


    /**
     * 测评结果（分享）
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sharedAssessResult(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'record_id',
                'type' => 'required',
                'error_code' => 'recordId_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $result = AIPlayReportService::getAssessResult($params['record_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $result);
    }


}