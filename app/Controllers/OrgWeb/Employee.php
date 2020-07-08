<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/7/8
 * Time: 3:38 PM
 */

namespace App\Controllers\OrgWeb;

use App\Libs\DictConstants;
use App\Models\EmployeeModel;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\HttpHelper;
use App\Controllers\ControllerBase;


class Employee extends ControllerBase
{
    public function getDeptMembers(Request $request, Response $response)
    {
        $params = $request->getParams();

        $roleId = DictConstants::get(DictConstants::ORG_WEB_CONFIG, $params['role_type']);
        if (empty($params['dept_id']) || empty($roleId)) {
            $members = [];
        } else {
            $members = EmployeeModel::getRecords(
                ['dept_id' => $params['dept_id'], 'role_id' => $roleId],
                ['id', 'uuid', 'name', 'role_id', 'status', 'dept_id', 'is_leader']
            );
        }

        //返回数据
        return HttpHelper::buildResponse($response, ['members' => $members]);
    }

}