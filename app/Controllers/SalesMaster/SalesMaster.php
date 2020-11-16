<?php

namespace App\Controllers\SalesMaster;


use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Models\StudentModel;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class SalesMaster extends ControllerBase
{
    public function dataReceived(Request $request, Response $response)
    {
        $rules = [
            [
                "key" => "wechatNumber",
                "type" => "required",
                "error_code" => "wechat_number_is_required"
            ]
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $mobile = $params['mobile'];
        $wechatNumber = $params['wechatNumber'];
        if (!empty($mobile)) {
            $student = StudentModel::getStudentByMobile($mobile);
            if (!empty($student)) {
                StudentModel::updateStudent($student['id'], ['wechatNumber' => $wechatNumber]);
            }
        }

        $resp = [
            'code'      => 0,
            'message'   => 'success',
            'data'      => NULL
        ];
        return $response->withJson($resp, StatusCode::HTTP_OK);
    }
}