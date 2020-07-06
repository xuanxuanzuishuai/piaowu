<?php
namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\AutoReplyAnswerModel;
use App\Models\AutoReplyQuestionModel;
use App\Services\AutoReplyService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class AutoReply extends ControllerBase
{
    public function questionAdd(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'title',
                'type' => 'required',
                'error_code' => 'title_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            AutoReplyService::addQuestion($params['title'], self::getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    public function questionEdit(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'title',
                'type' => 'required',
                'error_code' => 'title_is_required'
            ],
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            AutoReplyService::editQuestion($params['id'], $params['title'], $params['status']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    public function questionOne(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $detail = AutoReplyService::questionOne($params['id']);
        return HttpHelper::buildResponse($response, $detail);
    }

    public function answerAdd(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'q_id',
                'type' => 'required',
                'error_code' => 'q_id_is_required'
            ],
            [
                'key' => 'answer',
                'type' => 'required',
                'error_code' => 'answer_is_required'
            ],
            [
                'key' => 'sort',
                'type' => 'required',
                'error_code' => 'sort_is_required'
            ],
            [
                'key' => 'type',
                'type' => 'required',
                'error_code' => 'type_is_required'
            ],

        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $question = AutoReplyQuestionModel::getRecord(['status' => 1, 'id' => $params['q_id']]);
            if(empty($question)){
                return HttpHelper::buildOrgWebErrorResponse($response, "q_id_failed");
            }
            AutoReplyService::addAnswer($params['q_id'], $params['answer'], $params['sort'], $params['type']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    public function answerEdit(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'q_id',
                'type' => 'required',
                'error_code' => 'q_id_is_required'
            ],
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ],
            [
                'key' => 'answer',
                'type' => 'required',
                'error_code' => 'answer_is_required'
            ],
            [
                'key' => 'sort',
                'type' => 'required',
                'error_code' => 'sort_is_required'
            ],
            [
                'key' => 'status',
                'type' => 'required',
                'error_code' => 'status_is_required'
            ],
            [
                'key' => 'type',
                'type' => 'required',
                'error_code' => 'type_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            AutoReplyService::editAnswer($params['id'], $params['q_id'], $params['status'], $params['answer'], $params['sort'], $params['type']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    public function answerOne(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $detail = AutoReplyService::answerOne($params['id']);
        return HttpHelper::buildResponse($response, $detail);
    }

    public function list(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'page',
                'type'       => 'integer',
                'error_code' => 'page_must_be_integer'
            ],
            [
                'key'        => 'count',
                'type'       => 'integer',
                'error_code' => 'count_must_be_integer'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        list($page, $count) = Util::formatPageCount($params);
        list($question, $total) = AutoReplyService::getQuestionList($params['key'], $page, $count);
        $data['question'] = array_values($question);
        $data['total_count'] = $total;
        return HttpHelper::buildResponse($response, $data);
    }

}