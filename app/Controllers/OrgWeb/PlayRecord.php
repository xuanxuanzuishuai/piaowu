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
use App\Services\AIPlayRecordService;
use Slim\Http\Request;
use Slim\Http\Response;

class PlayRecord extends ControllerBase
{
    public function playStatistics(Request $request, Response $response)
    {
        $params = $request->getParams();

        $employeeId = self::getEmployeeId();
        $roleId = self::getRoleId();
        list($records, $totalCount) = AIPlayRecordService::studentPlayStatistics($params, $employeeId, $roleId);

        return HttpHelper::buildResponse($response, [
            'records' => $records,
            'total_count' => $totalCount
        ]);

    }

}