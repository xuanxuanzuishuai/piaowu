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
use App\Libs\Valid;
use App\Models\AppConfigModel;
use App\Libs\SimpleLogger;
use App\Services\HomeworkService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Libs\OpernCenterLib;
use App\Models\HomeworkTaskModel;

class HomeWork extends ControllerBase
{
    const version = "1.4";
    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function add(Request $request, Response $response, $args)
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
                'type' => 'required',
                'error_code' => 'schedule_id_is_required'
            ],
            [
                'key' => 'days_limit',
                'type' => 'required',
                'error_code' => 'days_limit_is_required'
            ],
            [
                'key' => 'remark',
                'type' => 'required',
                'error_code' => 'remark_is_required'
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

        // $user_id = $this->ci['user']['id'];
        // todo 因为这里微信端登陆的逻辑还没做，暂时先写死
        $user_id = 460;
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $homework_id = HomeworkService::createHomework($params["schedule_id"], $params["org_id"], $user_id, $params["student_id"],
            $params["days_limit"], $params["remark"], $params["content"]);

        $db->commit();

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ["id" => $homework_id]
        ], StatusCode::HTTP_OK);

    }

    /** 获取最近的教程
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getRecentCollections(Request $request, Response $response, $args){
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

         $erp = new OpernCenterLib();
        // $user_id = $this->ci['user']['id'];
        // todo 因为这里微信端登陆的逻辑还没做，暂时先写死
        $user_id = 460;

        $collection_ids = HomeworkTaskModel::getRecentCollectionIds($user_id, $params["page"], $params["limit"]);
        // $collection_ids = [22, 23, 24];
        $collection_list = [];
        if (!empty($collection_ids)) {
            $res = $erp->collectionsByIds(self::version, $collection_ids);
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
     * @param $args
     * @return Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getRecentLessons(Request $request, Response $response, $args){
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

        $erp = new OpernCenterLib();
        // $user_id = $this->ci['user']['id'];
        // todo 因为这里微信端登陆的逻辑还没做，暂时先写死
        $user_id = 460;

        $lesson_ids = HomeworkTaskModel::getRecentLessonIds($user_id, $params["page"], $params["limit"]);
        // $collection_ids = [22, 23, 24];
        $lesson_list = [];
        if (!empty($lesson_ids)) {
            // todo 这里等待杜老师提供接口
            $res = $erp->collectionsByIds(self::version, $lesson_ids);
            if (!empty($res['code']) && $res['code'] !== Valid::CODE_SUCCESS) {
                $lesson_list = [];
            } else {
                $lesson_list = $res["data"];
            }
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [] // todo 暂时先返回空
        ], StatusCode::HTTP_OK);
    }

    /** 模糊查询合集
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function searchCollections(Request $request, Response $response, $args){
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

        $erp = new OpernCenterLib();
        $res = $erp->searchCollections(self::version, $params["keyword"], $params["page"], $params["limit"], 0);
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
     * @param $args
     * @return Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function searchLessons(Request $request, Response $response, $args){
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

        $erp = new OpernCenterLib();
        $res = $erp->searchLessons(self::version, $params["keyword"], $params["page"], $params["limit"], 0, 1);
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

    /** 获取某个集合下的课程
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getLessons(Request $request, Response $response, $args){
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

        $erp = new OpernCenterLib();
        $res = $erp->lessonsList($params["page"], $params["limit"], self::version, $params["collection_id"]);
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

    /** 获取作业标准
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function getHomeworkDemand(Request $request, Response $response, $args){

        $result = AppConfigModel::get(AppConfigModel::AI_HOMEWORK_DEMAND_KEY);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => json_decode($result)
        ], StatusCode::HTTP_OK);
    }
}
