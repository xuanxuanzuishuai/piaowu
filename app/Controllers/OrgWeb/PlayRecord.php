<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/5/20
 * Time: 6:55 PM
 */

namespace App\Controllers\OrgWeb;


use App\Controllers\ControllerBase;

use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\AIPlayRecordService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class PlayRecord extends ControllerBase
{
    public function playStatistics(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'play_start_time',
                'type' => 'required',
                'error_code' => 'play_start_time_is_required'
            ],
            [
                'key' => 'play_end_time',
                'type' => 'required',
                'error_code' => 'play_end_time_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $employeeId = self::getEmployeeId();
        $roleId = self::getRoleId();
        list($records, $totalCount) = AIPlayRecordService::studentPlayStatistics($params, $employeeId, $roleId);

        return HttpHelper::buildResponse($response, [
            'total_count' => $totalCount,
            'records' => $records
        ]);

    }

}