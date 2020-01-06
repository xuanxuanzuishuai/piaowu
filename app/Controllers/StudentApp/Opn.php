<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/19
 * Time: 1:16 PM
 */

namespace App\Controllers\StudentApp;


use App\Controllers\ControllerBase;
use App\Libs\DictConstants;
use App\Libs\OpernCenter;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\FlagsService;
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

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT,
            $this->ci['opn_pro_ver'],
            $this->ci['opn_auditing'],
            $this->ci['opn_publish']);
        $collections = $opn->searchCollections($params['key'], 1, 1, 50);
        if (empty($collections) || !empty($collections['errors'])) {
            return $response->withJson($collections, StatusCode::HTTP_OK);
        }
        $collections = OpernService::appFormatCollections($collections['data']['list']);

        list($pageId, $pageLimit) = Util::appPageLimit($params);
        $result = $opn->searchLessons($params['key'], 1, 1, $pageId, $pageLimit);
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
            $this->ci['opn_pro_ver'],
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
            $this->ci['opn_pro_ver'],
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
            $this->ci['opn_pro_ver'],
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

    public function lessonLimit(Request $request, Response $response)
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
            ],
            [
                'key' => 'resource_type',
                'type' => 'required',
                'error_code' => 'resource_type_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT,
            $this->ci['opn_pro_ver'],
            $this->ci['opn_auditing'],
            $this->ci['opn_publish']);

        $newScoreFlagId = DictConstants::get(DictConstants::FLAG_ID, 'new_score');
        $student = $this->ci['student'];
        $student['version'] = $this->ci['version'];
        $student['platform'] = $this->ci['platform'];
        $useNewScore = FlagsService::hasFlag($student, $newScoreFlagId);


        $result = $opn->lessonsByIds($params['lesson_id'], 1, $params['resource_type']);
        if (empty($result) || !empty($result['errors'])) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $data = $result['data'];
        $limit = empty($params['limit']) ? 1 : $params;

        $lesson = OpernService::appFormatLessonByIds($data, $limit)[0] ?? [];

        if (!$lesson['is_free']) {
            $this->ci['need_res_privilege'] = true;
        }

        $resTestFlagId = DictConstants::get(DictConstants::FLAG_ID, 'res_free');
        if (FlagsService::hasFlag($this->ci['student'], $resTestFlagId)) {
            $this->ci['need_res_privilege'] = false;
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $lesson
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
            $this->ci['opn_pro_ver'],
            $this->ci['opn_auditing'],
            $this->ci['opn_publish']);

        $newScoreFlagId = DictConstants::get(DictConstants::FLAG_ID, 'new_score');
        $student = $this->ci['student'];
        $student['version'] = $this->ci['version'];
        $student['platform'] = $this->ci['platform'];
        $useNewScore = FlagsService::hasFlag($student, $newScoreFlagId);
        if (!$useNewScore) {
            $params['resource_types'] = 'dynamic';
        }

        $result = $opn->lessonsByIds($params['lesson_id'], 1, $params['resource_types']);
        if (empty($result) || !empty($result['errors'])) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $data = $result['data'];
        $lesson = OpernService::appFormatLessonByIds($data)[0] ?? [];

        if (!$lesson['is_free']) {
            $this->ci['need_res_privilege'] = true;
        }

        $resTestFlagId = DictConstants::get(DictConstants::FLAG_ID, 'res_free');
        if (FlagsService::hasFlag($this->ci['student'], $resTestFlagId)) {
            $this->ci['need_res_privilege'] = false;
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $lesson
        ], StatusCode::HTTP_OK);
    }

    public function lessonResources(Request $request, Response $response)
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
            ],
            [
                'key' => 'type',
                'type' => 'required',
                'error_code' => 'type_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT,
            $this->ci['opn_pro_ver'],
            $this->ci['opn_auditing'],
            $this->ci['opn_publish']);
        $result = $opn->lessonsByIds($params['lesson_id'], 1, $params['type']);
        if (empty($result) || !empty($result['errors'])) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result
        ], StatusCode::HTTP_OK);
    }

    public function engine(Request $request, Response $response)
    {
        Util::unusedParam($request);

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT,
            $this->ci['opn_pro_ver'],
            $this->ci['opn_auditing'],
            $this->ci['opn_publish']);
        $result = $opn->engine();
        if (empty($result) || !empty($result['errors'])) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $result
        ], StatusCode::HTTP_OK);
    }
}