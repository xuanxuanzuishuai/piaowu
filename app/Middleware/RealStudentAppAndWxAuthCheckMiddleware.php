<?php
/**
 * 真人业务线学生微信端接口路由文件
 * Class StudentWXRouter
 * @package App\Routers
 */

namespace App\Middleware;

use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Models\Erp\ErpStudentAppModel;
use Slim\Http\Request;
use App\Libs\Valid;
use Slim\Http\StatusCode;
use Slim\Http\Response;

/**
 * 真人学生app端中间件
 * Class RealStudentWeChatAuthCheckMiddleware
 * @package App\Middleware
 */
class RealStudentAppAndWxAuthCheckMiddleware extends MiddlewareBase
{

    public function __invoke(Request $request, Response $response, $next)
    {
        //获取header头中账户ID数据
        $userId = (int)$request->getHeader('UserId')[0];
        if (empty($userId)) {
            SimpleLogger::info('header user id miss: ', []);
            return $response->withJson(Valid::addAppErrors([], 'user_id_miss'), StatusCode::HTTP_OK);
        }
        $studentAppData = ErpStudentAppModel::getRecord(['student_id' => $userId], ['student_id(user_id)', 'status', 'first_pay_time']);
        if (empty($studentAppData)) {
            SimpleLogger::info('user data error: ', []);
            return $response->withJson(Valid::addAppErrors([], 'unknown_user'), StatusCode::HTTP_OK);
        }

        //获取header头中账户ID数据
        $fromType = $request->getHeader('FromType')[0];
        if (empty($fromType)) {
            SimpleLogger::info('header from type miss: ', []);
            return $response->withJson(Valid::addAppErrors([], 'from_type_miss'), StatusCode::HTTP_OK);
        } elseif (!in_array($fromType, [Constants::FROM_TYPE_REAL_STUDENT_APP, Constants::FROM_TYPE_REAL_STUDENT_WX])) {
            return $response->withJson(Valid::addAppErrors([], 'from_type_error'), StatusCode::HTTP_OK);
        }

        //设置账户ID到全局容器中
        $this->container['from_type'] = $fromType;
        $this->container['user_info'] = $studentAppData;
        $response = $next($request, $response);
        return $response;
    }
}
