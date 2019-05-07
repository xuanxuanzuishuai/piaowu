<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/21
 * Time: 10:54 AM
 */

namespace App\Middleware;

use App\Libs\SimpleLogger;
use App\Models\AppVersionModel;
use App\Services\AppVersionService;
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

        if ($this->container['platform'] == AppVersionService::PLAT_IOS) {
            $reviewVersion = AppVersionService::getReviewVersionCode(AppVersionModel::APP_TYPE_STUDENT,
                AppVersionService::getPlatformId(AppVersionService::PLAT_IOS));
            $isReviewVersion = ($reviewVersion == $this->container['version']);
        } else {
            $isReviewVersion = false;
        }
        $this->container['is_review_version'] = $isReviewVersion;

        SimpleLogger::info(__FILE__ . ":" . __LINE__ . " AppApiForStudent", [
            'platform' => $this->container['platform'] ?? NULL,
            'version' => $this->container['version'] ?? NULL,
            'is_review_version' => $this->container['is_review_version'] ?? NULL,
            'token' => $this->container['token'] ?? NULL,
        ]);

        return $next($request, $response);
    }
}