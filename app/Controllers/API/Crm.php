<?php

namespace App\Controllers\API;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Services\AIReferralToPandaUserService;

use App\Services\StudentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;


class Crm extends ControllerBase
{

    /**
     * 智能定向导流真人用户导入接口
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function exportAIReferralUser(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'uuids',
                'type' => 'required',
                'error_code' => 'student_ids_is_required',
            ],
            [
                'key' => 'student_type',
                'type' => 'required',
                'error_code' => 'student_type_is_required',
            ],
        ];

        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $studentUuids = explode(',', $params['uuids']);
        $students = StudentService::getByUuids($studentUuids, ['id']);
        $result = AIReferralToPandaUserService::addRecords($students, $params['student_type']);
        if (isset($result['code']) && $result['code'] === Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['count' => $result]
        ], StatusCode::HTTP_OK);
    }
}
