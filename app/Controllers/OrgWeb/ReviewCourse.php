<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/11/20
 * Time: 5:14 PM
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Services\AIBackendService;
use App\Services\ReviewCourseService;
use App\Services\ReviewCourseTaskService;
use Slim\Http\Request;
use Slim\Http\Response;

class ReviewCourse extends ControllerBase
{
    /**
     * 点评课学生列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function students(Request $request, Response $response)
    {
        $params = $request->getParams();

        $filter = ReviewCourseService::studentsFilter($params);
        list($count, $students) = ReviewCourseService::students($filter);

        return HttpHelper::buildResponse($response, [
            'total_count' => $count,
            'students' => $students
        ]);
    }

    /**
     * 点评课学生日报列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function studentReports(Request $request, Response $response)
    {
        $params = $request->getParams();

        try {
            $studentInfo = ReviewCourseService::studentInfo($params['student_id']);

            $filter = ReviewCourseService::reportsFilter($params);
            list($count, $reports) = ReviewCourseService::reports($filter);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, [
            'student' => $studentInfo,
            'total_count' => $count,
            'reports' => $reports
        ]);
    }

    /**
     * 点评课学生日报详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function studentReportDetail(Request $request, Response $response)
    {
        $params = $request->getParams();

        try {
            $studentInfo = ReviewCourseService::studentInfo($params['student_id']);

            $filter = ReviewCourseService::reportDetailFilter($params);
            $reports = ReviewCourseService::reportDetail($filter);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, [
            'student' => $studentInfo,
            'report_detail' => $reports
        ]);
    }

    /**
     * 点评课学生日报详情动态演奏
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function studentReportDetailDynamic(Request $request, Response $response)
    {
        $params = $request->getParams();

        try {
            $filter = ReviewCourseService::reportDetailFilter($params);
            $detailDynamic = ReviewCourseService::reportDetailDynamic($filter);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, ['detail_dynamic' => $detailDynamic]);
    }

    /**
     * 点评课学生日报详情AI测评
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function studentReportDetailAI(Request $request, Response $response)
    {
        $params = $request->getParams();

        try {
            $filter = ReviewCourseService::reportDetailFilter($params);
            $detailAI = ReviewCourseService::reportDetailAI($filter);

            if (isset($params['student_id'])) {
                $token = AIBackendService::genStudentToken($params['student_id']);
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, ['detail_ai' => $detailAI, 'token' => $token ?? '']);
    }

    /**
     * 发送点评
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function simpleReview(Request $request, Response $response)
    {
        $params = $request->getParams();

        try {
            ReviewCourseService::simpleReview($params['student_id'],
                $params['reviewer_id'],
                $params['date'],
                $params['audio']);

        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 点评任务列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function tasks(Request $request, Response $response)
    {
        $params = $request->getParams();

        list($total, $tasks) = ReviewCourseTaskService::getTasks($params);

        return HttpHelper::buildResponse($response, ['total_count' => $total, 'tasks' => $tasks]);
    }

    /**
     * 点评课配置
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function config(/** @noinspection PhpUnusedParameterInspection */ Request $request, Response $response)
    {
        $config = ReviewCourseTaskService::getConfig();

        return HttpHelper::buildResponse($response, ['config' => $config]);
    }

    /**
     * 演奏详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function playDetail(Request $request, Response $response)
    {
        $params = $request->getParams();

        try {
            $detail = ReviewCourseTaskService::getPlayDetailByReviewTask($params['task_id']);

        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $detail);
    }

    /**
     * 上传点评语音
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function uploadReviewAudio(Request $request, Response $response)
    {
        $params = $request->getParams();

        try {
            ReviewCourseTaskService::uploadReviewAudio($params['task_id'], $params['audio']);

        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 发送点评
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sendReview(Request $request, Response $response)
    {
        $params = $request->getParams();

        try {
            $success = ReviewCourseService::sendTaskReview($params['task_id']);

        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, ['ret' => $success]);
    }
}