<?php
/**
 * Created by PhpStorm.
 * User: dahua
 * Date: 2019/4/26
 * Time: 17:30
 */

namespace App\Controllers\TeacherApp;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Models\AppVersionModel;
use App\Services\AppVersionService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Version extends ControllerBase {

    public function version(Request $request, Response $response){
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'platform',
                'type' => 'required',
                'error_code' => 'platform_is_required'
            ],
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $ret = AppVersionModel::lastVersion($params['platform'], AppVersionModel::APP_TYPE_TEACHER);
        if (empty($ret)) {
            $result = Valid::addAppErrors([], 'no_available_version');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $version = [
            'code' => $ret['version'],
            'desc' => $ret['ver_desc'],
            'force_update' => $ret['force_update'],
            'download_url' => $ret['download_url']
        ];

        return $response->withJson([
            'code'=> Valid::CODE_SUCCESS,
            'data'=> [
                'version' => $version
            ],
        ], StatusCode::HTTP_OK);
    }

    public function hotFix(Request $request, Response $response) {
        $platform = $request->getHeader('platform');
        $platform = $platform[0] ?? AppVersionService::PLAT_UNKNOWN;
        $version = $request->getHeader('version');
        $version = $version[0] ?? '';

        $platformId = AppVersionService::getPlatformId($platform);
        $lastVersion = AppVersionService::getLastVersion($platformId, $version);
        $hotfix = AppVersionService::getHotfixConfig($platformId, $version);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'version' => $lastVersion,
                'hotfix' => $hotfix,
            ],
            'meta' => [
                'code' => 0,
            ]
        ], StatusCode::HTTP_OK);
    }
}
