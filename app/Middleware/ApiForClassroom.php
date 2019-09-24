<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/9/24
 * Time: 5:52 PM
 */

namespace App\Middleware;

use App\Libs\SimpleLogger;
use Slim\Http\Request;
use Slim\Http\Response;

class ApiForClassroom extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        $version = $request->getHeaderLine('version');
        $this->container['version'] = empty($version) ? NULL : $version;

        $orgToken = $request->getHeaderLine('org-token');
        $this->container['org_token'] = empty($orgToken) ? NULL : $orgToken;

        $orgTeacherToken = $request->getHeaderLine('org-teacher-token');
        $this->container['org_teacher_token'] = empty($orgTeacherToken) ? NULL : $orgTeacherToken;

        SimpleLogger::info(__FILE__ . ":" . __LINE__ . " ApiForClassroom", [
            'version' => $this->container['version'] ?? NULL,
            'org_token' => $this->container['org_token'] ?? NULL,
            'org_teacher_token' => $this->container['org_teacher_token'] ?? NULL,
        ]);

        return $next($request, $response);
    }
}