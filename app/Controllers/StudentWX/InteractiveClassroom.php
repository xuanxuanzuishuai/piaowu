<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/18
 * Time: 8:51 PM
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\OpernCenter;
use App\Libs\Valid;
use App\Models\StudentLearnRecordModel;
use App\Models\StudentModel;
use App\Services\InteractiveClassroomService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class InteractiveClassroom extends ControllerBase
{
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

        $studentId = $this->ci['user_info']['user_id'];
        $student = StudentModel::getById($studentId);

        if (empty($student)) {
            HttpHelper::buildResponse($response, []);
        }

        $opn = new OpernCenter(OpernCenter::PRO_ID_INTERACTION_CLASSROOM, OpernCenter::version);
        $learnCalendar = InteractiveClassroomService::getLearnCalendar($opn, $studentId, $params["year"], $params["month"]);

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
            ],
            [
                'key' => 'day',
                'type' => 'required',
                'error_code' => 'day_is_required'
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
        $opn = new OpernCenter(OpernCenter::PRO_ID_INTERACTION_CLASSROOM, OpernCenter::version);
        $dateTime = strtotime($params['year'].'-'.$params['month'].'-'.$params['day']);
        $calendarData = InteractiveClassroomService::studentCoursePlan($opn, $studentId, $dateTime);
        $classStatus['class_count'] = count($calendarData) ?? 0; //今日总课数
        $lessonLearnStatus = array_count_values(array_column($calendarData, 'lesson_learn_status'));

        reset($lessonLearnStatus);
        while (list($key, $val) = each($lessonLearnStatus)) {
            if($key == StudentLearnRecordModel::FINISH_LEARNING || $key == StudentLearnRecordModel::MAKE_UP_LESSONS){
                $classStatus['finish_learning'] = $val ?? 0; //完成上课
            } elseif($key == StudentLearnRecordModel::TO_MAKE_UP_LESSONS) {
                $classStatus['to_make_up_lesson'] = $val ?? 0; //待补课
            }elseif($key == StudentLearnRecordModel::GO_TO_THE_CLASS) {
                $classStatus['go_to_the_class'] = $val ?? 0; //去上课
            }
        }
        return HttpHelper::buildResponse($response, $classStatus);
    }

    /**
     * 互动课堂分享课程
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function shareClassInformation(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'jwt',
                'type' => 'required',
                'error_code' => 'jwt_is_required'
            ],
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
        try {
            $opn = new OpernCenter(OpernCenter::PRO_ID_INTERACTION_CLASSROOM, OpernCenter::version);
            $report = InteractiveClassroomService::shareClassInformation($params['jwt'], $params['collection_id'], $opn);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $report,
        ], StatusCode::HTTP_OK);

    }

}