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
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;

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

    /**
     * 用户报名
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function collectionSignUp(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'collection_id',
                'type'       => 'required',
                'error_code' => 'collection_id_is_required',
            ],
            [
                'key'        => 'lesson_count',
                'type'       => 'required',
                'error_code' => 'lesson_count_is_required',
            ],
            [
                'key'        => 'start_week',
                'type'       => 'required',
                'error_code' => 'start_week_is_required',
            ],
            [
                'key'        => 'start_time',
                'type'       => 'required',
                'error_code' => 'start_time_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $studentId = $this->ci['student']['id'];
        try {
            InteractiveClassroomService::collectionSignUp($studentId, $params['collection_id'], $params['lesson_count'], $params['start_week'], $params['start_time']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [],
        ], StatusCode::HTTP_OK);
    }

    /**
     * 用户取消报名
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function cancelSignUp(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'collection_id',
                'type'       => 'required',
                'error_code' => 'collection_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $studentId = $this->ci['student']['id'];
        try {
            InteractiveClassroomService::cancelSignUp($studentId, $params['collection_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [],
        ], StatusCode::HTTP_OK);
    }

    /**
     * 用户上课记录，只有完成上课or完成补课才会调用此接口
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function studentLearnRecord(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'collection_id',
                'type'       => 'required',
                'error_code' => 'collection_id_is_required',
            ],
            [
                'key'        => 'lesson_id',
                'type'       => 'required',
                'error_code' => 'lesson_id_is_required',
            ],
            [
                'key'        => 'learn_status',
                'type'       => 'required',
                'error_code' => 'lesson_status_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $studentId = $this->ci['student']['id'];

        try {
            InteractiveClassroomService::studentLearnRecode($studentId, $params['collection_id'], $params['lesson_id'], $params['learn_status']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [],
        ], StatusCode::HTTP_OK);
    }

}