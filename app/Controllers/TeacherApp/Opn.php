<?php
/**
 * Created by PhpStorm.
 * User: dahua
 * Date: 2019/4/22
 * Time: 15:27
 */


namespace App\Controllers\TeacherApp;


use App\Controllers\ControllerBase;
use App\Libs\OpernCenter;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\AppConfigModel;
use App\Models\HomeworkTaskModel;
use App\Services\OpernService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Opn extends ControllerBase
{
    public function search(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'key',
                'type' => 'required',
                'error_code' => 'keyword_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $version = $this->ci['version'];

        if ($this->ci['opn_is_tester']) {
            $auditing = 0;
            $publish = 0;
        } else {
            $reviewVersion = AppConfigModel::get(AppConfigModel::REVIEW_VERSION);
            $auditing = ($reviewVersion == $params['version']) ? 1 : 0;
            $publish = 1;
        }

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, $version, $auditing, $publish);
        $collections = $opn->searchCollections($params['key'], 1, 1, 50);
        if (empty($collections) || !empty($collections['errors'])) {
            return $response->withJson($collections, StatusCode::HTTP_OK);
        }
        $collections = OpernService::appFormatCollections($collections['data']['list']);

        list($pageId, $pageLimit) = Util::appPageLimit($params);
        $result = $opn->searchLessons($params['key'], 1, 0, $pageId, $pageLimit);
        if (empty($result) || !empty($result['errors'])) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $lessons = OpernService::appSearchLessons($result['data']['list']);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'collections' => $collections,
                'lessons' => $lessons,
                'lesson_count' => $result['data']['total_count']
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取系列列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function categories(Request $request, Response $response)
    {
        $params = $request->getParams();

        list($pageId, $pageLimit) = Util::appPageLimit($params);
        // TODO 请求erp的参数
        /**
        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_TEACHER,
            $this->ci['version'],
            $this->ci['opn_auditing'],
            $this->ci['opn_publish']);
         */
        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, 2);
        $result = $opn->categories($pageId, $pageLimit);
        if (empty($result) || !empty($result['errors'])) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $data = $result['data'];
        $list = OpernService::formatCategories($data['list']);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'categories' => $list,
                'category_count' => $data['total_count']
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取书籍列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function collections(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'category_id',
                'type' => 'required',
                'error_code' => 'category_id_is_required'
            ],
            [
                'key' => 'category_id',
                'type' => 'numeric',
                'error_code' => 'category_id_must_be_numeric'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        list($pageId, $pageLimit) = Util::appPageLimit($params);
        /**
        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_TEACHER,
            $this->ci['version'],
            $this->ci['opn_auditing'],
            $this->ci['opn_publish']);
        */
        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, 1);
        $result = $opn->collections($params['category_id'], $pageId, $pageLimit);
        if (empty($result) || !empty($result['errors'])) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $data = $result['data'];
        $list = OpernService::appFormatCollections($data['list']);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'collections' => $list,
                'collection_count' => $data['total_count']
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取课程列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function lessons(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'collection_id',
                'type' => 'required',
                'error_code' => 'collection_id_is_required'
            ],
            [
                'key' => 'collection_id',
                'type' => 'numeric',
                'error_code' => 'collection_id_must_be_numeric'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        list($pageId, $pageLimit) = Util::appPageLimit($params);
        /**
        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT,
            $this->ci['version'],
            $this->ci['opn_auditing'],
            $this->ci['opn_publish']);
         */
        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, 1);
        $result = $opn->lessons($params['collection_id'], $pageId, $pageLimit);
        if (empty($result) || !empty($result['errors'])) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $data = $result['data'];
        $list = OpernService::appFormatLessons($data['list']);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'lessons' => $list,
                'lesson_count' => $data['total_count']
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取课程详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function lesson(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'lesson_id',
                'type' => 'required',
                'error_code' => 'lesson_id_is_required'
            ],
            [
                'key' => 'lesson_id',
                'type' => 'numeric',
                'error_code' => 'lesson_id_must_be_numeric'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        /**
        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT,
            $this->ci['version'],
            $this->ci['opn_auditing'],
            $this->ci['opn_publish']);
         */
        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, 1);
        $result = $opn->lessonsByIds($params['lesson_id']);
        if (empty($result) || !empty($result['errors'])) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        SimpleLogger::debug("******========--------", [$result]);
        // TODO 曲谱库这个接口没有data层，应该为和其他接口一样
        // $data = $result['data'];
        $data = $result;
        $list = OpernService::appFormatLessonByIds($data['data'])[0] ?? [];
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $list
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取老师最近使用书籍
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    public function recentCollections(Request $request, Response $response){
        $params = $request->getParams();
        list($pageId, $pageLimit) = Util::formatPageCount($params);
        // $userId = $this->ci['teacher']['id'];
        $userId = 460;
        $collectionIds = HomeworkTaskModel::getRecentCollectionIds($userId, $pageId, $pageLimit);
        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, 1);
        $result = $opn->collectionsByIds($collectionIds);
        if (empty($result) || !empty($result['errors'])) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data = $result;
        $list = OpernService::appFormatCollections($data['data']);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $list
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取老师最近课程
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    public function recentLessons(Request $request, Response $response){
        $params = $request->getParams();
        list($pageId, $pageLimit) = Util::formatPageCount($params);
        // $userId = $this->ci['teacher']['id'];
        $userId = 460;
        $lessonIds = HomeworkTaskModel::getRecentLessonIds($userId, $pageId, $pageLimit);

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, 1);
        $result = $opn->lessonsByIds($lessonIds);
        if (empty($result) || !empty($result['errors'])) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $data = $result;
        $list = OpernService::appFormatLessonByIds($data['data']);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $list
        ], StatusCode::HTTP_OK);
    }


    /**
     * 获取课程资源
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    public function getLessonResource(Request $request, Response $response){
        // 验证请求参数
        $rules = [
            [
                'key' => 'lesson_ids',
                'type' => 'required',
                'lesson_ids_is_required' => 'lesson_ids_is_required'
            ]
        ];
        $param = $request->getParams();
        $result = Valid::validate($param, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $lessonIds = $param['lesson_ids'];
        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_TEACHER, 1);
        $result = $opn->lessonsByIds($lessonIds, 'png');
        SimpleLogger::debug("*******************", $result);
        if (empty($result) || !empty($result['errors'])) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $data = $result;
        $list = [];
        foreach ($data['data'] as $lesson) {
            $opern =[];
            $opern['lesson_id'] = $lesson['lesson_id'];
            $opern['res'] = $lesson['resources'];
            $list[] = $opern;
        }
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $list
        ], StatusCode::HTTP_OK);
    }
}