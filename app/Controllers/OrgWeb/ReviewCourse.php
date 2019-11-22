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
use App\Services\ReviewCourseService;
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
        $students = ReviewCourseService::students($filter);

        return HttpHelper::buildResponse($response, ['students' => $students]);
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
            $reports = ReviewCourseService::reports($filter);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, [
            'student' => $studentInfo,
            'reports' => $reports
        ]);
    }

    public function studentReportsDetail(Request $request, Response $response)
    {

    }
}