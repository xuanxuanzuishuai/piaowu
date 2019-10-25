<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/10/22
 * Time: 下午6:03
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\QuestionTagService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class QuestionTag extends ControllerBase
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
                'key'        => 'tag',
                'type'       => 'required',
                'error_code' => 'tag_is_required'
            ],
            [
                'key'        => 'tag',
                'type'       => 'lengthMax',
                'value'      => 8,
                'error_code' => 'tag_must_elt_8'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $employeeId = $this->ci['employee']['id'];

        $tag = str_replace(',', '', $params['tag']);

        try {
            $record = QuestionTagService::addEdit($params['id'], $tag, $employeeId);
        }catch(RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $record);
    }

    //只返回status为正常的tag
    public function tags(Request $request, Response $response)
    {
        $key = $request->getParam("key");
        return HttpHelper::buildResponse($response, QuestionTagService::tags($key));
    }

    //todo 所有tags
    public function list(Request $request, Response $response)
    {

    }
}