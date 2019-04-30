<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 11:03
 */

namespace App\Controllers\TeacherWX;


use App\Controllers\ControllerBase;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\AppConfigModel;
use App\Models\HomeworkModel;
use App\Models\PlayRecordModel;
use App\Services\HomeworkService;
use App\Libs\OpernCenter;
use App\Models\HomeworkTaskModel;
use Predis\Command\Redis\SINTER;
use Slim\Http\Request;
use App\Services\OpernService;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class HomeWork extends ControllerBase
{
    const version = "1.4";
    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function add(Request $request, Response $response)
    {

        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required'
            ],
            [
                'key' => 'org_id',
                'type' => 'required',
                'error_code' => 'org_id_is_required'
            ],
            [
                'key' => 'schedule_id',
                'type' => 'integer',
                'error_code' => 'schedule_id_is_required'
            ],
            [
                'key' => 'days_limit',
                'type' => 'required',
                'error_code' => 'days_limit_is_required'
            ],
            [
                'key' => 'content',
                'type' => 'required',
                'error_code' => 'content_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $user_id = $this->ci['user_info']['user_id'];
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $homework_id = HomeworkService::createHomework($params["schedule_id"], $params["org_id"], $user_id, $params["student_id"],
            $params["days_limit"], $params["remark"] ?? "", $params["content"]);

        $db->commit();

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ["id" => $homework_id]
        ], StatusCode::HTTP_OK);

    }

    /**
     * 获取最近的教程
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getRecentCollections(Request $request, Response $response)
    {
        $rules = [

            [
                'key' => 'page',
                'type' => 'integer',
                'error_code' => 'page_must_be_integer'
            ],
            [
                'key' => 'limit',
                'type' => 'integer',
                'error_code' => 'limit_must_be_integer'
            ],
            [
                'key' => 'student_id',
                'type' => 'integer',
                'error_code' => 'student_id_must_be_integer'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $page = $params["page"] ?? 1;
        $limit = $params["limit"] ?? 20;
        $user_id = $this->ci['user_info']['user_id'];
        $collection_ids = HomeworkTaskModel::getRecentCollectionIds($user_id, $page,
            $limit, $params["student_id"]);
        $collection_list = [];
        if (!empty($collection_ids)) {
            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, self::version);
            $res = $opn->collectionsByIds($collection_ids);
            if (!empty($res['code']) && $res['code'] !== Valid::CODE_SUCCESS) {
                $collection_list = [];
            } else {
                $collection_list = $res["data"];
            }
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $collection_list
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取最近的课程
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getRecentLessons(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'page',
                'type' => 'integer',
                'error_code' => 'page_must_be_integer'
            ],
            [
                'key' => 'limit',
                'type' => 'integer',
                'error_code' => 'limit_must_be_integer'
            ],
            [
                'key' => 'student_id',
                'type' => 'integer',
                'error_code' => 'student_id_must_be_integer'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $page = $params["page"] ?? 1;
        $limit = $params["limit"] ?? 20;
        $user_id = $this->ci['user_info']['user_id'];
        $lesson_ids = HomeworkTaskModel::getRecentLessonIds($user_id, $page, $limit, $params["student_id"]);
        $lesson_list = [];
        if (!empty($lesson_ids)) {
            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, self::version);
            $res = $opn->lessonsByIds($lesson_ids);
            if (!empty($res['code']) && $res['code'] !== Valid::CODE_SUCCESS) {
                $lesson_list = [];
            } else {
                $lesson_list = $res["data"];
            }
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $lesson_list
        ], StatusCode::HTTP_OK);
    }

    /**
     * 模糊查询合集
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function searchCollections(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'keyword',
                'type' => 'required',
                'error_code' => 'name_is_required'
            ],
            [
                'key' => 'page',
                'type' => 'required',
                'error_code' => 'page_is_required'
            ],
            [
                'key' => 'limit',
                'type' => 'required',
                'error_code' => 'limit_is_required'
            ],
            [
                'key' => 'page',
                'type' => 'integer',
                'error_code' => 'page_must_be_integer'
            ],
            [
                'key' => 'limit',
                'type' => 'integer',
                'error_code' => 'limit_must_be_integer'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        if ($params["keyword"] == "") {
            return $response->withJson([
                'code' => Valid::CODE_SUCCESS,
                'data' => []
            ], StatusCode::HTTP_OK);
        }

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, self::version);
        $res = $opn->searchCollections($params["keyword"], 1, $params["page"], $params["limit"]);
        if (!empty($res['code']) && $res['code'] !== Valid::CODE_SUCCESS) {
            $result = [];
        } else {
            $result = $res["data"];
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result
        ], StatusCode::HTTP_OK);
    }

    /**
     * 模糊查询课程
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function searchLessons(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'keyword',
                'type' => 'required',
                'error_code' => 'name_is_required'
            ],
            [
                'key' => 'page',
                'type' => 'required',
                'error_code' => 'page_is_required'
            ],
            [
                'key' => 'limit',
                'type' => 'required',
                'error_code' => 'limit_is_required'
            ],
            [
                'key' => 'page',
                'type' => 'integer',
                'error_code' => 'page_must_be_integer'
            ],
            [
                'key' => 'limit',
                'type' => 'integer',
                'error_code' => 'limit_must_be_integer'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        if ($params["keyword"] == "") {
            return $response->withJson([
                'code' => Valid::CODE_SUCCESS,
                'data' => []
            ], StatusCode::HTTP_OK);
        }

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, self::version);
        $res = $opn->searchLessons($params["keyword"], 0, 1, $params["page"], $params["limit"]);
        if (!empty($res['code']) && $res['code'] !== Valid::CODE_SUCCESS) {
            $result = [];
        } else {
            $result = $res["data"];
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取某个集合下的课程
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getLessons(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'collection_id',
                'type' => 'required',
                'error_code' => 'collection_id_is_required'
            ],
            [
                'key' => 'page',
                'type' => 'required',
                'error_code' => 'page_is_required'
            ],
            [
                'key' => 'limit',
                'type' => 'required',
                'error_code' => 'limit_is_required'
            ],
            [
                'key' => 'page',
                'type' => 'integer',
                'error_code' => 'page_must_be_integer'
            ],
            [
                'key' => 'limit',
                'type' => 'integer',
                'error_code' => 'limit_must_be_integer'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, self::version);
        $res = $opn->lessons($params["collection_id"], $params["page"], $params["limit"]);
        if (!empty($res['code']) && $res['code'] !== Valid::CODE_SUCCESS) {
            $lesson_list = [];
        } else {
            $lesson_list = $res["data"];
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $lesson_list
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取作业标准
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getHomeworkDemand(Request $request, Response $response)
    {
        $result = AppConfigModel::get(AppConfigModel::AI_HOMEWORK_DEMAND_KEY);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => json_decode($result)
        ], StatusCode::HTTP_OK);
    }

    /**
     * 作业练习记录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getHomeworkPlayRecordList(Request $request, Response $response){
        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required'
            ],
            [
                'key' => 'student_id',
                'type' => 'integer',
                'error_code' => 'student_id_must_be_integer'
            ],
            [
                'key' => 'page',
                'type' => 'integer',
                'error_code' => 'page_must_be_integer'
            ],
            [
                'key' => 'limit',
                'type' => 'integer',
                'error_code' => 'limit_must_be_integer'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $user_id = $this->ci['user_info']['user_id'];
//        $user_id = null;
        if(empty($params["page"])){
            $params["page"] = 1;
        }
        if (empty($params["limit"])){
            $params["limit"] = 10;
        }

        $data = HomeworkService::getStudentHomeWorkList($params["student_id"],
            $user_id, $params["page"], $params["limit"]);
        $temp = [];
        $current_time = time();
        foreach ($data as $homework){
            // 以homework为单位聚合task
            $homeworkId = $homework['id'];
            $task = [
                'task_id' => $homework['task_id'],
                'lesson_id' => $homework['lesson_id'],
                'complete' => $homework['complete'],
                'lesson_name' => $homework['lesson_name'],
                'duration' => 0,
                'play_count' => 0,
                'max_score' => 0,
            ];

            $playRecordStatistic = PlayRecordModel::getPlayRecordListByHomework($homeworkId, $homework['task_id'], $homework['lesson_id'],
                $homework['created_time'], $homework['end_time'], true);
            $task["duration"] = $playRecordStatistic["duration"];
            $task["play_count"] = $playRecordStatistic["play_count"];
            $task["max_score"] = $playRecordStatistic["max_score"];

            if(array_key_exists($homeworkId, $temp)){
                array_push($temp[$homeworkId]['tasks'], $task);
            }else{
                $temp[$homeworkId] = [
                    'start_date' => date("Y-m-d", $homework['created_time']),
                    'end_date' => date("Y-m-d", $homework['end_time']),
                    'homework_id' => $homework['id'],
                    'tasks' => [$task],
                    'out_of_date' => true ? $homework["end_time"] <= $current_time : false
                ];
            }
        }

        $returnData = [];
        foreach ($temp as $k=>$v){
            if(!empty($v)){
                array_push($returnData, $v);
            }
        }
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $returnData
        ], StatusCode::HTTP_OK);
    }

    /**
     * 测评成绩单
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getTaskDetail(Request $request, Response $response){
        $rules = [
            [
                'key' => 'task_id',
                'type' => 'required',
                'error_code' => 'task_id_is_required'
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
//        $user_id = null;

        list($homework, $play_record) = HomeworkService::getStudentHomeworkPractice(null, $params['task_id'], $user_id);
        if(empty($homework)){
            $errors = Valid::addAppErrors([], "homework_not_found");
            return $response->withJson($errors, StatusCode::HTTP_OK);
        }
        $ret = [
            "lesson_name" => $homework["lesson_name"],
            "baseline" => $homework["baseline"],
        ];
        $format_record = [];
        $max_score_index_map = [];
        foreach ($play_record as $item) {
            $create_date = date("Y-m-d", $item["created_time"]);

            if ($item["complete"]){
                $item["tags"] = ["达成要求"];
            } else{
                $item["tags"] = [];
            }

            $item["created_time"] = date("Y-m-d H:i", $item["created_time"]);

            if(array_key_exists($create_date, $format_record)){
                // 更新最大得分index
                if ($item["score"] > $format_record[$create_date]["max_score"]){
                    $max_score_index_map[$create_date] = sizeof($format_record[$create_date]['records']);
                }
                array_push($format_record[$create_date]['records'], $item);
            }else{
                $format_record[$create_date] = [
                    'create_date' => $create_date,
                    'records' => [$item],
                    'max_score' => $item["score"]
                ];
                $max_score_index_map[$create_date] = 0;
            }
        }

        foreach ($max_score_index_map as $date => $index){
            array_push($format_record[$date]["records"][$index]["tags"], "当日最高");
        }
        $ret["records"] = array_values($format_record);

        return $response->withJson(['code'=>0, 'data'=>$ret], StatusCode::HTTP_OK);
    }
}
