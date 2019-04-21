<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/18
 * Time: 8:51 PM
 */

namespace App\Controllers\StudentApp;


use App\Controllers\ControllerBase;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\AppConfigModel;
use App\Models\FeedbackModel;
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
        $lastVersion = AppVersionService::getLastVersion($platformId, $this->ci['version']);
        $hotfix = AppVersionService::getHotfixConfig($platformId, $this->ci['version']);

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

    public function guide(Request $request, Response $response)
    {
        Util::unusedParam($request);

        $version = $this->ci['version'];
        if ($version == AppConfigModel::get('REVIEW_VERSION')) {
            $url = AppConfigModel::get('REVIEW_GUIDE_URL');
        } else {
            $url = AppConfigModel::get('GUIDE_URL');
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'url' => $url
            ]
        ], StatusCode::HTTP_OK);
    }

    public function feedback(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'content',
                'type' => 'required',
                'error_code' => 'opinion_content_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $userId = $this->ci['student']['id'];
        $data = [
            'user_type' => FeedbackModel::TYPE_STUDENT,
            'user_id' => $userId,
            'content' => $params['content'],
            'platform' => $this->ci['platform'],
            'version' => $this->ci['version'],
            'create_time' => time()
        ];
        FeedbackModel::insertRecord($data);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => []
        ], StatusCode::HTTP_OK);
    }

    public function oldVersion(Request $request, Response $response)
    {
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

        $platformId = AppVersionService::getPlatformId($params['platform']);
        $lastVersion = AppVersionService::getLastVersion($platformId, '');

        return $response->withJson([
            'code'=> Valid::CODE_SUCCESS,
            'data'=> [
                'version' => $lastVersion
            ],
        ], StatusCode::HTTP_OK);
    }
}