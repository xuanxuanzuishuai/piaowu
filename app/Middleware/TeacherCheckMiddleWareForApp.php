<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/17
 * Time: 2:11 PM
 */

namespace App\Middleware;

use App\Libs\SimpleLogger;
use App\Libs\Valid;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class TeacherCheckMiddleWareForApp extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        if (empty($this->container['teacher'])) {
            SimpleLogger::error(__FILE__ . __LINE__, ['empty_teacher_token']);
            $result = Valid::addAppErrors([], 'empty_teacher_token');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $response = $next($request, $response);

        return $response;

    }
}