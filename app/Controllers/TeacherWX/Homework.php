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
use App\Services\HomeworkService;
use App\Libs\OpernCenter;
use App\Models\HomeworkTaskModel;
use Slim\Http\Request;
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

    /** 获取最近的教程
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getRecentCollections(Request $request, Response $response)
    {
        $rules = [
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

        $user_id = $this->ci['user_info']['user_id'];
        $collection_ids = HomeworkTaskModel::getRecentCollectionIds($user_id, $params["page"], $params["limit"]);
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

    /** 获取最近的课程
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getRecentLessons(Request $request, Response $response)
    {
        $rules = [
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

        $user_id = $this->ci['user_info']['user_id'];
        $lesson_ids = HomeworkTaskModel::getRecentLessonIds($user_id, $params["page"], $params["limit"]);
        $lesson_list = [];
        if (!empty($lesson_ids)) {
            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, self::version);
            $res = $opn->lessonsByIds($lesson_ids);
            if (!empty($res['code']) && $res['code'] !== Valid::CODE_SUCCESS) {
                $lesson_list = [];
            } else {
                $lesson_list = $res["data"] ? $res["data"]: $res;
            }
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $lesson_list
        ], StatusCode::HTTP_OK);
    }

    /** 模糊查询合集
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

    /** 模糊查询课程
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
}
