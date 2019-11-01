<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/10/30
 * Time: 下午6:02
 */

namespace App\Controllers\ExamMinApp;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Services\QuestionService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\Valid;
use Slim\Http\StatusCode;

class Question extends ControllerBase
{
    public function list(Request $request, Response $response)
    {
        $records = QuestionService::questions();
        return HttpHelper::buildResponse($response, $records);
    }

    public function detail(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'id',
                'type'       => 'required',
                'error_code' => 'id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $record = QuestionService::getByIdForApp($params['id']);

        return HttpHelper::buildResponse($response, $record);
    }
}