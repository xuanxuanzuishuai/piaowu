<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/6
 * Time: 11:09 AM
 */

namespace App\Middleware;


use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Services\StudentServiceForApp;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class StudentResPrivilegeCheckMiddleWareForApp extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        /** @var Response $response */
        $response = $next($request, $response);

        if (empty($this->container['need_res_privilege'])) {
            return $response;
        }

        $student = $this->container['student'];

        if (!StudentServiceForApp::getSubStatus($student['id'])) {
            SimpleLogger::error(__FILE__ . ":" . __LINE__, ['no_res_privilege' => $student]);
            $result = Valid::addAppErrors([], 'no_res_privilege');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        SimpleLogger::info(__FILE__ . ":" . __LINE__, [
            'middleWare' => 'StudentResPrivilegeCheckMiddleWareForApp',
            'student' => $student
        ]);

        return $response;
    }
}