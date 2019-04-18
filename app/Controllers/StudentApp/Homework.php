<?php
/**
 * Created by PhpStorm.
 * User: dahua
 * Date: 2019/4/17
 * Time: 16:40
 */

namespace App\Controllers\Homework;

use App\Controllers\ControllerBase;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\HomeworkService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

/**
 * 学生作业
 */
class Homework extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    public function homeworkRecord(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'opern_id',
                'type' => 'required',
                'opern_id_is_required' => 'opern_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        list($pageId, $pageLimit) = Util::formatPageCount($params);
        $userId = $this->user['id'];
        $data = HomeworkService::getHomework($userId, $params['opern_id'], $pageId, $pageLimit);
        return $response->withJson($data, StatusCode::HTTP_OK);
    }
}