<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/7/29
 * Time: 4:05 PM
 */

namespace app\Middleware;

use App\Services\StudentService;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use Slim\Http\Request;
use Slim\Http\StatusCode;
use Slim\Http\Response;


class WeChatPandaAuthCheckMiddleware extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        SimpleLogger::info('--WeChatPandaAuthCheckMiddleware--', []);

        $uuid = $request->getParam('uuid');
        $student = StudentService::getByUuid($uuid);
        if (empty($student)) {
            return $response->withJson(Valid::addAppErrors([], 'unknown_user'), StatusCode::HTTP_OK);
        }

        $this->container['student'] = $student;
        SimpleLogger::info('UserInfo: ', ["student" => $student]);

        $response = $next($request, $response);
        return $response;
    }
}