<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/24
 * Time: 11:09
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\AIPLCenter;
use App\Libs\OpernCenter;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Services\HomeworkService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Services\PlayRecordService;


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
     * 精彩回放
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getWonderfulMomentUrl(Request $request, Response $response){
        $rules = [
            [
                'key' => 'ai_record_id',
                'type' => 'required',
                'error_code' => 'ai_record_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data = AIPLCenter::userAudio($params["ai_record_id"]);
        if (empty($data) or $data["meta"]["code"] != 0){
            $ret = [];
        } else {
            $ret = ["url" => $data["data"]["audio_url"]];
        }
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $ret
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取练琴记录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getPlayRecordList(Request $request, Response $response)
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
        $result = PlayRecordService::getDayPlayRecordStatistic($user_id, $params["date"]);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result
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
            ],
            [
                'key' => 'task_id',
                'type' => 'integer',
                'error_code' => 'task_id_must_be_integer'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $user_id = $this->ci['user_info']['user_id'];
//        $user_id = 87;

        $lesson_name = "";
        $baseline = null;

        // 优先使用task_id
        if (!empty($params["task_id"])){
            list($homework, $play_record) = HomeworkService::getStudentDayHomeworkPractice(null,
                $params['task_id'], $user_id, $params["date"]);
            if(empty($homework)){
                $errors = Valid::addAppErrors([], "homework_not_found");
                return $response->withJson($errors, StatusCode::HTTP_OK);
            }
            $lesson_name = $homework["lesson_name"];
            $baseline = $homework["baseline"];

            $records = PlayRecordService::formatLessonTestStatistics($play_record);
        } else {
            // 如果没有传task_id则按照lesson_id为准
            $play_record = HomeworkService::getStudentDayLessonPractice($user_id, $params["lesson_id"], $params["date"]);
            $records = PlayRecordService::formatLessonTestStatistics($play_record);
            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_TEACHER, OpernCenter::version);
            $bookInfo = $opn->lessonsByIds([$params["lesson_id"]]);
            if (!empty($bookInfo) and $bookInfo["code"] == 0){
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
     * 获取测评评分
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getAIRecordGrade(Request $request, Response $response){
        $rules = [
            [
                'key' => 'ai_record_id',
                'type' => 'required',
                'error_code' => 'ai_record_id_is_required'
            ],
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

        $data = AIPLCenter::recordGrade($params["ai_record_id"]);

        if (empty($data) or $data["meta"]["code"] != 0){
            $score_ret = [];
        } else {
            $score = $data["data"]["score"];
            $score_ret = [
                "simple_complete" => $score["simple_complete"],
                "simple_final" => $score["simple_final"],
                "simple_pitch" => $score["simple_pitch"],
                "simple_rhythm" => $score["simple_rhythm"],
                "simple_speed_average" => $score["simple_speed_average"],
                "simple_speed" => $score["simple_speed"]
            ];
        }
        $data = AIPLCenter::userAudio($params["ai_record_id"]);
        if (empty($data) or $data["meta"]["code"] != 0){
            $wonderful_url = "";
        } else {
            $wonderful_url = $data["data"]["audio_url"];
        }

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, OpernCenter::version);
        $res = $opn->lessonsByIds([$params["lesson_id"]]);
        if (!empty($res['code']) && $res['code'] !== Valid::CODE_SUCCESS) {
            $opern_list = [];
        } else {
            $opern_id = $res["data"][0]["opern_id"];
            $result = $opn->staticResource($opern_id, 'png');
            if (!empty($result['code']) && $result['code'] !== Valid::CODE_SUCCESS){
                $opern_list = [];
            } else {
                $opern_list = $result["data"];
            }
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                "score" => $score_ret,
                "wonderful_url" => $wonderful_url,
                "opern_list" => $opern_list
            ]
        ], StatusCode::HTTP_OK);
    }
}
