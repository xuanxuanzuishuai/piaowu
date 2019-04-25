<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/21
 * Time: 10:54 AM
 */

namespace App\Middleware;

use App\Libs\SimpleLogger;
use Slim\Http\Request;
use Slim\Http\Response;

class AppApi extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        // getHeaderLine 方法找不到对应header时会返回空数组，不能直接扔进container
        $platform = $request->getHeaderLine('platform');
        $this->container['platform'] = empty($platform) ? NULL : $platform;

        $version = $request->getHeaderLine('version');
        $this->container['version'] = empty($version) ? NULL : $version;

        $token = $request->getHeaderLine('token');
        $this->container['token'] = empty($token) ? NULL : $token;

        $orgToken = $request->getHeaderLine('org-token');
        $this->container['org_token'] = empty($orgToken) ? NULL : $orgToken;

        $orgTeacherToken = $request->getHeaderLine('org-teacher-token');
        $this->container['org_teacher_token'] = empty($orgTeacherToken) ? NULL : $orgTeacherToken;

        SimpleLogger::info(__FILE__ . ":" . __LINE__ . " App api middleware", [
            'platform' => $this->container['platform'] ?? NULL,
            'version' => $this->container['version'] ?? NULL,
            'token' => $this->container['token'] ?? NULL,
            'org_token' => $this->container['org_token'] ?? NULL,
            'org_teacher_token' => $this->container['org_teacher_token'] ?? NULL,
        ]);

        return $next($request, $response);
    }
}