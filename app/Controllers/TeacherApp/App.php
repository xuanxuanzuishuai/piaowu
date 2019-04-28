<?php
/**
 * Created by PhpStorm.
 * User: dahua
 * Date: 2019/4/22
 * Time: 15:28
 */

namespace App\Controllers\TeacherApp;

use App\Controllers\ControllerBase;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\AppVersionModel;
use App\Services\AppVersionService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class App extends ControllerBase
{
    public function version(Request $request, Response $response)
    {
        Util::unusedParam($request);

        $platformId = AppVersionService::getPlatformId($this->ci['platform']);
        $lastVersion = AppVersionService::getLastVersion(AppVersionModel::APP_TYPE_TEACHER, $platformId, $this->ci['version']);
        $hotfix = AppVersionService::getHotfixConfig(AppVersionModel::APP_TYPE_TEACHER, $this->ci['version']);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'version' => $lastVersion,
                'hotfix' => $hotfix,
            ]
        ], StatusCode::HTTP_OK);
    }
}