<?php
namespace App\Controllers\StudentApp;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Services\AIPlayRecordService;
use App\Services\CreditService;
use App\Services\InteractiveClassroomService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class InteractiveClassroom extends ControllerBase
{
    /**
     * 获取可报名课程
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getSignUpCourse(Request $request, Response $response)
    {
        $studentId = $this->ci['student']['id'];
        $CourseSignUpList = InteractiveClassroomService::getSignUpCourse($studentId);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $CourseSignUpList,
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取待上线课包
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getToBeLaunchedCourse(Request $request, Response $response)
    {
        $result = InteractiveClassroomService::getToBeLaunchedCourse();
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result,
        ], StatusCode::HTTP_OK);
    }

    /**
     * 制作中课程
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getInProductionCourse(Request $request, Response $response)
    {
        $result = InteractiveClassroomService::getInProductionCourse();
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result,
        ], StatusCode::HTTP_OK);

    }

    /**
     * 获取今日计划
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function studentTodayPlan(Request $request, Response $response)
    {
        $studentId = $this->ci['student']['id'];
        //练琴任务
        list($generalTask,$finishTheTask) = CreditService::getActivityList($studentId);
        $result['sum_duration'] = AIPlayRecordService::getStudentSumDuration($studentId);
        //完成练琴任务次数
        $result['finish_the_task'] = (INT)$finishTheTask;
        //总任务
        $result['general_task'] = $generalTask;
        //今日课程
        list($todayCourse, $todayRecommendCourse) = InteractiveClassroomService::getTodayCourse($studentId);
        $result['today_course'] = $todayCourse;
        $result['today_recommend_course'] = $todayRecommendCourse;

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result,
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取小喇叭轮播信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function smallHornInfo(Request $request, Response $response)
    {
        $smallHornInfo = InteractiveClassroomService::getSmallHornInfo();
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $smallHornInfo,
        ], StatusCode::HTTP_OK);
    }
}