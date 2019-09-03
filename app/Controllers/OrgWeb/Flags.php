<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/9/3
 * Time: 2:47 PM
 */

namespace App\Controllers\OrgWeb;


use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\FlagsServices;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Flags extends ControllerBase
{
    public function list(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'status',
                'type' => 'in',
                'value' => [Constants::STATUS_TRUE, Constants::STATUS_FALSE],
                'error_code' => 'status_format_error'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $data = FlagsServices::list($params);

        return HttpHelper::buildResponse($response, $data);
    }

    public function add(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'name_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $operator = $this->ci['employee']['id'];
        $desc = $params['desc'] ?? '';

        try {
            $data = FlagsServices::add($params['name'], $desc, $operator);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $data);
    }

    public function modify(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ],
            [
                'key' => 'data',
                'type' => 'required',
                'error_code' => 'data_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $operator = $this->ci['employee']['id'];

        try {
            $data = FlagsServices::modify($params['id'], $params['data'], $operator);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $data);
    }
}