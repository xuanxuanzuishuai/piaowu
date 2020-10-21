<?php
namespace App\Controllers\StudentApp;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Util;
use App\Libs\OpernCenter;
use App\Libs\Valid;
use App\Models\AIPlayRecordModel;
use App\Models\StudentModel;
use App\Services\AIBackendService;
use App\Services\AIPlayRecordService;
use App\Services\AIPlayReportService;
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
        $opn = new OpernCenter(OpernCenter::PRO_ID_INTERACTION_CLASSROOM, OpernCenter::version);
        $CourseSignUpList = InteractiveClassroomService::getSignUpCourse($opn, $studentId);

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
        $params = $request->getParams();
        $studentId = $this->ci['student']['id'];
        $opn = new OpernCenter(OpernCenter::PRO_ID_INTERACTION_CLASSROOM, OpernCenter::version);
        $result = InteractiveClassroomService::getToBeLaunchedCourse($opn, $studentId, $params['page']);
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
        $params = $request->getParams();
        $opn = new OpernCenter(OpernCenter::PRO_ID_INTERACTION_CLASSROOM, OpernCenter::version);
        $result = InteractiveClassroomService::getInProductionCourse($opn, $params['page']);
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
        $result['finish_the_task'] = !empty($generalTask) ? (INT)$finishTheTask : 0;//完成练琴任务次数
        //总任务
        $result['general_task'] = !empty($generalTask) ? $generalTask : 0;//总任务
        $opn = new OpernCenter(OpernCenter::PRO_ID_INTERACTION_CLASSROOM, OpernCenter::version);
        //今日课程
        list($todayCourse, $todayRecommendCourse) = InteractiveClassroomService::getTodayCourse($opn, $studentId);
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
        $studentId = $this->ci['student']['id'];

        $opn = new OpernCenter(OpernCenter::PRO_ID_INTERACTION_CLASSROOM, OpernCenter::version);
        $smallHornInfo = InteractiveClassroomService::getSmallHornInfo($opn, $studentId);
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
            ],
            [
                'key'        => 'learn_time',
                'type'       => 'required',
                'error_code' => 'learn_time_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $studentId = $this->ci['student']['id'];

        try {
            InteractiveClassroomService::studentLearnRecode($studentId, $params['collection_id'], $params['lesson_id'], $params['learn_status'], $params['learn_time']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [],
        ], StatusCode::HTTP_OK);
    }

    /**
     * 练琴月历
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getPlayCalendar(Request $request, Response $response)
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

        $studentId = $this->ci['student']['id'];
        $student = StudentModel::getById($studentId);

        if (empty($student)) {
            HttpHelper::buildResponse($response, []);
        }

        $calendar = AIPlayReportService::getPlayCalendar($studentId, $params["year"], $params["month"]);

        return HttpHelper::buildResponse($response, ['calendar' => $calendar]);
    }

    /**
     * 上课月历表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getLearnCalendar(Request $request, Response $response)
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

        $studentId = $this->ci['student']['id'];
        $student = StudentModel::getById($studentId);

        if (empty($student)) {
            HttpHelper::buildResponse($response, []);
        }

        $learnCalendar = InteractiveClassroomService::getLearnCalendar($studentId, $params["year"], $params["month"]);

        return HttpHelper::buildResponse($response, ['learn_calendar' => $learnCalendar]);
    }


    /**
     * 获取课程详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getCalendarDetails(Request $request, Response $response)
    {

        $params = $request->getParams();

        $studentId = $this->ci['student']['id'];
        $opn = new OpernCenter(OpernCenter::PRO_ID_INTERACTION_CLASSROOM, OpernCenter::version);
        $calendarDetails = InteractiveClassroomService::getCalendarDetails($opn, $studentId, $params["year"], $params["month"], $params['day']);
        return HttpHelper::buildResponse($response, ['calendar_details' => $calendarDetails]);
    }

    /**
     * 分享课程报告需要token
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function studentShareToken(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'collection_id',
                'type' => 'required',
                'error_code' => 'collection_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $studentId = $this->ci['student']['id'];
        $student = StudentModel::getById($studentId);
        if (empty($student)) {
            HttpHelper::buildResponse($response, []);
        }
        $report["share_token"] = AIPlayReportService::getShareReportToken($studentId, date('Ymd'));
        $report['replay_token'] = AIBackendService::genStudentToken($studentId);
        $opn = new OpernCenter(OpernCenter::PRO_ID_INTERACTION_CLASSROOM, OpernCenter::version);
        $collectionData = InteractiveClassroomService::erpCollectionByIds($opn, $params['collection_id']);
        $report['collection_cover'] = $collectionData['collection_cover'];
        $report['collection_name'] = $collectionData['collection_name'];
        $accumulateDays = Util::dateDiff(date('Y-m-d', $student['create_time']), date('Y-m-d',time()));
        $report['accumulate_days'] = !empty($accumulateDays) ? $accumulateDays : Constants::STATUS_TRUE;

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $report,
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取用户年卡身份
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getStudentIdentity(Request $request, Response $response)
    {
        $studentId = $this->ci['student']['id'];
        try {
            $result = InteractiveClassroomService::getStudentIdentity($studentId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result,
        ], StatusCode::HTTP_OK);
    }
    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 获取课程资源
     */
    public function lessonRecourse(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'lesson_id',
                'type' => 'required',
                'error_code' => 'lesson_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $opn = new OpernCenter(OpernCenter::PRO_ID_INTERACTION_CLASSROOM, OpernCenter::version);
        $data = InteractiveClassroomService::lessonRecourse($opn, $params['lesson_id']);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data,
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 期待记录接口
     */
    public function expect(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'collection_id',
                'type' => 'required',
                'error_code' => 'collection_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $studentId = $this->ci['student']['id'];
        InteractiveClassroomService::expect($studentId, $params['collection_id']);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [],
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 获取平台今明两天的开课计划
     */
    public function platformCoursePlan(Request $request, Response $response)
    {
        $opn = new OpernCenter(OpernCenter::PRO_ID_INTERACTION_CLASSROOM, OpernCenter::version);
        $studentId = $this->ci['student']['id'];
        $data = InteractiveClassroomService::platformCoursePlan($opn,$studentId);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data,
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 获取课包详情接口
     */
    public function collectionDetail(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'collection_id',
                'type' => 'required',
                'error_code' => 'collection_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $studentId = $this->ci['student']['id'];
        $opn = new OpernCenter(OpernCenter::PRO_ID_INTERACTION_CLASSROOM, OpernCenter::version);
        $data = InteractiveClassroomService::collectionDetail($opn,$params['collection_id'],$studentId);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data,
        ], StatusCode::HTTP_OK);
    }
}