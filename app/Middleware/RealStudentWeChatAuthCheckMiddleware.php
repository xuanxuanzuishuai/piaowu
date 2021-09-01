<?php
/**
 * 真人业务线学生微信端接口路由文件
 * Class StudentWXRouter
 * @package App\Routers
 */

namespace App\Middleware;

use App\Libs\Constants;
use App\Libs\Dss;
use App\Libs\SimpleLogger;
use App\Models\Erp\ErpStudentAppModel;
use App\Models\Erp\ErpStudentModel;
use App\Services\UserService;
use App\Services\WechatTokenService;
use Slim\Http\Request;
use App\Libs\Valid;
use Slim\Http\StatusCode;
use Slim\Http\Response;

/**
 * 真人学生微信端中间件
 * Class RealStudentWeChatAuthCheckMiddleware
 * @package App\Middleware
 */
class RealStudentWeChatAuthCheckMiddleware extends MiddlewareBase
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

        //设置账户ID到全局容器中
        $this->container['user_info'] = $studentAppData;
        $response = $next($request, $response);
        return $response;
    }
}
