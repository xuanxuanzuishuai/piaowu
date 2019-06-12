<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/6/27
 * Time: 下午7:52
 */

namespace App\Middleware;

use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\EmployeeModel;
use Slim\Http\Request;
use Slim\Http\Response;

class EmployeeAuthCheckMiddleWare extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        /** @var array $token */
        $token = $request->getHeader('token');
        if (empty($token) && $request->getMethod() == 'GET'){
            $params = $request->getParams();
            $token[0] = $params['token'] ?? '';
        }
        SimpleLogger::info('token',[$token]);

        if (empty($token) || empty($token[0])) {
            // token为空时检查是否是开发环境，如果是，设置一个默认的登录用户
            if(isset($_ENV['ENV_NAME']) && $_ENV['ENV_NAME'] == 'dev' && !empty($_ENV['DEV_EMPLOYEE_ID'])) {
                $cacheEmployeeId = $_ENV['DEV_EMPLOYEE_ID'];
            }else{
                SimpleLogger::error(__FILE__ . __LINE__, ['code' => 'JWT is empty', 'errs' => []]);
                return $response->withJson(Valid::addErrors(['code' => -1], 'auth_check1', 'token_can_not_empty'));
            }
        }else{
            $token = $token[0];
            $cacheEmployeeId = EmployeeModel::getEmployeeToken($token);
        }

        if (empty($cacheEmployeeId)) {
            SimpleLogger::error(__FILE__ . ":" . __LINE__, ['code' => 'Employee had logouted', 'errs' => []]);
            return $response->withJson(Valid::addErrors(['code' => -1], 'auth_check', 'employee_has_logout'));
        }

        $employee = EmployeeModel::getById($cacheEmployeeId);
        // 延长登录token过期时间
        EmployeeModel::refreshEmployeeCache($token);
        $this->container['employee'] = $employee;
        global $orgId;
        $orgId = $employee['org_id'];

        SimpleLogger::info(__FILE__ . ":" . __LINE__, [
            'middleWare' => 'EmployeeAuthCheckMiddleWare',
            'employee' => $employee
        ]);

        $response = $next($request, $response);

        return $response;
    }
}