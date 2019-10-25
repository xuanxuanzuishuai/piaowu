<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/10/22
 * Time: 下午6:01
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\QuestionCatalogService;
use App\Services\QuestionService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Question extends ControllerBase
{
    public function addEdit(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'id',
                'type'       => 'integer',
                'error_code' => 'id_is_integer'
            ],
            [
                'key'        => 'exam_org',
                'type'       => 'required',
                'error_code' => 'exam_org_is_required'
            ],
            [
                'key'        => 'exam_org',
                'type'       => 'integer',
                'error_code' => 'exam_org_must_integer'
            ],
            [
                'key'        => 'level',
                'type'       => 'required',
                'error_code' => 'level_is_required'
            ],
            [
                'key'        => 'level',
                'type'       => 'integer',
                'error_code' => 'level_must_integer'
            ],
            [
                'key'        => 'catalog',
                'type'       => 'required',
                'error_code' => 'catalog_is_required'
            ],
            [
                'key'        => 'catalog',
                'type'       => 'integer',
                'error_code' => 'catalog_must_integer'
            ],
            [
                'key'        => 'sub_catalog',
                'type'       => 'required',
                'error_code' => 'sub_catalog_is_required'
            ],
            [
                'key'        => 'sub_catalog',
                'type'       => 'integer',
                'error_code' => 'sub_catalog_must_integer'
            ],
            [
                'key'        => 'template',
                'type'       => 'required',
                'error_code' => 'template_is_required'
            ],
            [
                'key'        => 'template',
                'type'       => 'integer',
                'error_code' => 'template_must_integer'
            ],
            [
                'key'        => 'content_text',
                'type'       => 'lengthMax',
                'value'      => 127,
                'error_code' => 'content_text_elt_127'
            ],
            [
                'key'        => 'content_img',
                'type'       => 'lengthMax',
                'value'      => 255,
                'error_code' => 'content_img_elt_255'
            ],
            [
                'key'        => 'content_audio',
                'type'       => 'lengthMax',
                'value'      => 255,
                'error_code' => 'content_audio_elt_255'
            ],
            [
                'key'        => 'content_text_audio',
                'type'       => 'lengthMax',
                'value'      => 255,
                'error_code' => 'content_text_audio_elt_255'
            ],
            [
                'key'        => 'question_tag',
                'type'       => 'array',
                'error_code' => 'question_tag_must_be_array'
            ],
            [
                'key'        => 'audio_set',
                'type'       => 'array',
                'error_code' => 'audio_set_must_be_array'
            ],
            [
                'key'        => 'options',
                'type'       => 'array',
                'error_code' => 'options_must_be_array'
            ],
            [
                'key'        => 'answer_explain',
                'type'       => 'array',
                'error_code' => 'answer_explain_must_be_array'
            ],
            [
                'key'        => 'status',
                'type'       => 'required',
                'error_code' => 'status_is_required'
            ],
            [
                'key'        => 'status',
                'type'       => 'integer',
                'error_code' => 'status_is_integer'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $id = QuestionService::addEdit($this->ci['employee']['id'], $params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, ['last_id' => $id]);
    }

    public function list(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($page, $count) = Util::formatPageCount($params);

        list($records, $total) = QuestionService::selectByPage($page, $count, $params);

        return HttpHelper::buildResponse($response, [
            'records'     => $records,
            'total_count' => $total,
        ]);
    }

    public function detail(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'id',
                'type'       => 'integer',
                'error_code' => 'id_is_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $record = QuestionService::getById($params['id']);

        return HttpHelper::buildResponse($response, $record);
    }

    public function status(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'id',
                'type'       => 'array',
                'error_code' => 'id_is_array'
            ],
            [
                'key'        => 'status',
                'type'       => 'required',
                'error_code' => 'status_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            QuestionService::batchUpdateStatus($params['id'], $params['status'], time());
        }catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, []);
    }

    public function catalog(Request $request, Response $response)
    {
        $records = QuestionCatalogService::selectAll();

        return HttpHelper::buildResponse($response, $records);
    }
}