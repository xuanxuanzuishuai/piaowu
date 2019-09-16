<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/21
 * Time: 10:54 AM
 */

namespace App\Middleware;

use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use App\Services\FlagsService;
use Slim\Http\Request;
use Slim\Http\Response;

class AppApiForStudent extends MiddlewareBase
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

        // 在用户身份验证前，通过平台版本信息检查是否是审核版本
        $object = [
            'platform' => $this->container['platform'],
            'version' => $this->container['version'],
        ];
        $reviewFlagId = DictConstants::get(DictConstants::FLAG_ID, 'app_review');
        $reviewFlag = FlagsService::hasFlag($object, $reviewFlagId);
        if ($reviewFlag) {
            $response = $response->withHeader('app-review', 1);
        }

        $this->container['flags'] = [
            $reviewFlagId => $reviewFlag
        ];

        SimpleLogger::info(__FILE__ . ":" . __LINE__ . " AppApiForStudent", [
            'platform' => $this->container['platform'] ?? NULL,
            'version' => $this->container['version'] ?? NULL,
            'reviewFlag' => $reviewFlag,
            'token' => $this->container['token'] ?? NULL,
        ]);

        return $next($request, $response);
    }
}