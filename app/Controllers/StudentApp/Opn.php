<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/19
 * Time: 1:16 PM
 */

namespace App\Controllers\StudentApp;


use App\Controllers\ControllerBase;
use App\Libs\OpernCenter;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\AppConfigModel;
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

    public function categories(Request $request, Response $response)
    {
        $params = $request->getParams();

        list($pageId, $pageLimit) = Util::appPageLimit($params);

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT,
            $this->ci['version'],
            $this->ci['opn_auditing'],
            $this->ci['opn_publish']);
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

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT,
            $this->ci['version'],
            $this->ci['opn_auditing'],
            $this->ci['opn_publish']);
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

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT,
            $this->ci['version'],
            $this->ci['opn_auditing'],
            $this->ci['opn_publish']);
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

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT,
            $this->ci['version'],
            $this->ci['opn_auditing'],
            $this->ci['opn_publish']);
        $result = $opn->lessonsByIds($params['lesson_id']);
        if (empty($result) || !empty($result['errors'])) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        // TODO 曲谱库这个接口没有data层，应该为和其他接口一样
//        $data = $result['data'];
        $data = $result;
        $list = OpernService::appFormatLessonByIds($data['list']);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $list ?? []
        ], StatusCode::HTTP_OK);
    }
}