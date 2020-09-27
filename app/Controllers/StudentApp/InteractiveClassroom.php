<?php
namespace App\Controllers\StudentApp;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
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
}