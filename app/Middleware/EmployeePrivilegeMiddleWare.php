<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/6/27
 * Time: 下午6:23
 */

namespace App\Middleware;

use App\Models\PrivilegeModel;
use App\Services\EmployeePrivilegeService;
use Slim\Http\Request;
use App\Libs\Valid;
use Slim\Http\Response;

class EmployeePrivilegeMiddleWare extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        //验证权限
        $method = $request->getMethod();
        $pathStr = $request->getUri()->getPath();
        $pathStr = preg_replace('/\/[0-9]+/', '/', $pathStr);
        $privilege = PrivilegeModel::getPIdByUri($pathStr, $method);
        $pIds = EmployeePrivilegeService::getEmployeePIds($this->container['employee']);

        //超级管理员跳过权限 roleid －1
        if (EmployeePrivilegeService::checkIsSuperAdmin($this->container['employee'])) {
            return $response = $next($request, $response);
        }

        if (EmployeePrivilegeService::hasPermission($privilege, $pIds, $pathStr, $method)) {
            return $response = $next($request, $response);
        } else {
            $errs = Valid::addErrors(['code'=> -1], 'author', 'no_privilege');
            return $response->withJson($errs, 200);
        }

    }
}

