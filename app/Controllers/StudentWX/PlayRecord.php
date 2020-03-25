<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/24
 * Time: 11:09
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\OpernCenter;
use App\Libs\Valid;
use App\Models\HomeworkTaskModel;
use App\Models\PlayRecordModel;
use App\Models\StudentModel;
use App\Models\StudentModelForApp;
use App\Services\HomeworkService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Services\PlayRecordService;
use App\Libs\Util;

class PlayRecord extends ControllerBase
{

    /**
     * 获取练琴日报
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function recordReport(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'date',
                'type' => 'required',
                'error_code' => 'date_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $user_id = $this->ci['user_info']['user_id'];
        $result = PlayRecordService::getDayRecordReport($user_id, $params["date"]);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result
        ], StatusCode::HTTP_OK);
    }

    /**
     * 分享报告页面
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function shareReport(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'jwt',
                'type' => 'required',
                'error_code' => 'jwt_token_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $data = PlayRecordService::parseShareReportToken($params["jwt"]);
        if ($data["code" != 0]) {
            $response->withJson(Valid::addAppErrors([], 'jwt_invalid'), StatusCode::HTTP_OK);
        }
        $result = PlayRecordService::getDayRecordReport($data["student_id"], $data["date"]);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result
        ], StatusCode::HTTP_OK);
    }

    /**
     * 查看指定学生单日测评
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getShareReport(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 's',
                'type' => 'required',
                'error_code' => 's_is_required'
            ],
            [
                'key' => 'd',
                'type' => 'required',
                'error_code' => 'd_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $student = StudentModelForApp::getStudentInfo(null, $params['s']);
        if (empty($student)) {
            return $response->withJson(Valid::addAppErrors([], 's error!'), StatusCode::HTTP_OK);
        }
        $result = PlayRecordService::getDayRecordReport($student['id'], $params['d']);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result,
        ], StatusCode::HTTP_OK);
    }

    /**
     * 学生端获取测评成绩单
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getLessonTestStatistics(Request $request, Response $response){
        $rules = [
            [
                'key' => 'date',
                'type' => 'required',
                'error_code' => 'date_is_required'
            ],
            [
                'key' => 'lesson_id',
                'type' => 'required',
                'error_code' => 'lesson_id_is_required'
            ],
            [
                'key' => 'lesson_id',
                'type' => 'integer',
                'error_code' => 'lesson_id_must_be_integer'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $user_id = $this->ci['user_info']['user_id'];
        $lesson_name = "";
        $baseline = null;
        if (!empty($params["task_id"])) {
            list($homework, $play_record) = HomeworkService::getStudentDayHomeworkPractice($user_id,
                $params['task_id'], null, $params["date"]);
            if(empty($homework)){
                $errors = Valid::addAppErrors([], "homework_not_found");
                return $response->withJson($errors, StatusCode::HTTP_OK);
            }
            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_TEACHER, OpernCenter::version);
            $bookInfo = $opn->lessonsByIds([$params["lesson_id"]]);
            if (!empty($bookInfo) and $bookInfo["code"] == 0){
                $lesson_name = $bookInfo["data"][0]["lesson_name"];
            }

            $task = HomeworkTaskModel::getById($params["task_id"]);
            if (!empty($task)) {
                $baseline = $homework["baseline"];
            }

            $records = PlayRecordService::formatLessonTestStatistics($play_record);
        } else {
            $play_record = HomeworkService::getStudentDayLessonPractice($user_id, $params["lesson_id"], $params["date"]);
            $records = PlayRecordService::formatLessonTestStatistics($play_record);
            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_TEACHER, OpernCenter::version);
            $bookInfo = $opn->lessonsByIds([$params["lesson_id"]]);
            if (!empty($bookInfo) and $bookInfo["code"] == 0) {
                $lesson_name = $bookInfo["data"][0]["lesson_name"];
            }
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                "lesson_name" => $lesson_name,
                "baseline" => $baseline,
                "records" => $records
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 分享页面获取测评成绩单
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function shareLessonTestStatistics(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'jwt',
                'type' => 'required',
                'error_code' => 'jwt_is_required'
            ],
            [
                'key' => 'lesson_id',
                'type' => 'required',
                'error_code' => 'lesson_id_is_required'
            ],
            [
                'key' => 'lesson_id',
                'type' => 'integer',
                'error_code' => 'lesson_id_must_be_integer'
            ],
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

        $data = PlayRecordService::parseShareReportToken($params["jwt"]);
        if ($data["code" != 0]) {
            $response->withJson(Valid::addAppErrors([], 'jwt_invalid'), StatusCode::HTTP_OK);
        }
        $user_id = $data["student_id"];
        $date = $params["date"];

        $lesson_name = "";
        $baseline = null;
        // 优先使用task_id
        if (!empty($params["task_id"])) {
            list($homework, $play_record) = HomeworkService::getStudentDayHomeworkPractice($user_id,
                $params['task_id'], null, $params["date"]);
            if(empty($homework)){
                $errors = Valid::addAppErrors([], "homework_not_found");
                return $response->withJson($errors, StatusCode::HTTP_OK);
            }
            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_TEACHER, OpernCenter::version);
            $bookInfo = $opn->lessonsByIds([$params["lesson_id"]]);
            if (!empty($bookInfo) and $bookInfo["code"] == 0){
                $lesson_name = $bookInfo["data"][0]["lesson_name"];
            }
            $baseline = $homework["baseline"];

            $records = PlayRecordService::formatLessonTestStatistics($play_record);
        } else {
            $play_record = HomeworkService::getStudentDayLessonPractice($user_id, $params["lesson_id"], $date);
            $records = PlayRecordService::formatLessonTestStatistics($play_record);
            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_TEACHER, OpernCenter::version);
            $bookInfo = $opn->lessonsByIds([$params["lesson_id"]]);
            if (!empty($bookInfo) and $bookInfo["code"] == 0) {
                $lesson_name = $bookInfo["data"][0]["lesson_name"];
            }
        }
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                "lesson_name" => $lesson_name,
                "baseline" => $baseline,
                "records" => $records
            ]
        ], StatusCode::HTTP_OK);
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

        $calendar = PlayRecordService::getPlayCalendar($studentId, $params["year"], $params["month"]);

        return HttpHelper::buildResponse($response, ['calendar' => $calendar]);
    }
}
