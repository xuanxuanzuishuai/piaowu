<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/6/27
 * Time: 下午6:23
 */


/**
 * 登录控制
 * @author tianye@xiaoyezi.com
 * @since 2016-08-04 15:17:00
 */


namespace App\Middleware;

use App\Models\PrivilegeModel;
use App\Services\EmployeePrivilegeService;
use Psr\Container\ContainerInterface;
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
            $errs = Valid::addErrors([], 'author', 'no_privilege');
            $this->container['result'] = $errs;
            return $response;
        }

    }
}

