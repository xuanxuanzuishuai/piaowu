<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/11/6
 * Time: 上午10:47
 */

namespace App\Controllers\StudentApp;

use App\Libs\Valid;
use App\Services\QuestionService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use Slim\Http\StatusCode;

class Question extends ControllerBase
{
    public function baseList(Request $request, Response $response)
    {
        $records = QuestionService::baseQuestions();
        return HttpHelper::buildResponse($response, $records);
    }

    public function categoryRelateQuestions(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'cat_log_id',
                'type'       => 'required',
                'error_code' => 'cat_log_id_is_required'
            ],
            [
                'key'        => 'sub_cat_log_id',
                'type'       => 'required',
                'error_code' => 'sub_cat_log_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $records = QuestionService::getCatLogRelateQuestions($params['cat_log_id'], $params['sub_cat_log_id'], $params['need_num'] ?? NULL);
        return HttpHelper::buildResponse($response, $records);
    }

    public function list(Request $request, Response $response)
    {
        $records = QuestionService::questions();
        return HttpHelper::buildResponse($response, $records);
    }
}