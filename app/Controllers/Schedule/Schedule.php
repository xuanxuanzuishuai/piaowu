<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 17:57
 */

namespace App\Controllers\Schedule;


use App\Controllers\ControllerBase;
use App\Libs\Valid;
use Slim\Http\Request;
use Slim\Http\Response;

class Schedule extends ControllerBase
{
    public function list(Request $request, Response $response, $args)
    {
        $params = $request->getParams();

    }

    public function detail(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'schedule_id',
                'type' => 'required',
                'error_code' => 'schedule_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }

    }

    public function modify(Request $request, Response $response, $args)
    {

    }
}