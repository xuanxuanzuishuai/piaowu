<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/18
 * Time: 8:51 PM
 */

namespace App\Controllers\StudentApp;


use App\Controllers\ControllerBase;
use App\Libs\DictConstants;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\AppVersionModel;
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
        $lastVersion = AppVersionService::getLastVersion(AppVersionModel::APP_TYPE_STUDENT, $platformId, $this->ci['version']);
        $hotfix = AppVersionService::getHotfixConfig(AppVersionModel::APP_TYPE_STUDENT, $platformId, $this->ci['version']);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'version' => $lastVersion,
                'hotfix' => $hotfix,
            ]
        ], StatusCode::HTTP_OK);
    }

    public function config(Request $request, Response $response)
    {
        Util::unusedParam($request);

        $config = [];
        $config['ai_host'] = DictConstants::get(DictConstants::APP_CONFIG_COMMON, 'ai_host');
        $config['policy_url'] = DictConstants::get(DictConstants::APP_CONFIG_STUDENT, 'policy_url');

        if ($this->ci['is_review_version']) {
            $config['guide_url'] = DictConstants::get(DictConstants::APP_CONFIG_STUDENT, 'review_guide_url');
        } else {
            $config['guide_url'] = DictConstants::get(DictConstants::APP_CONFIG_STUDENT, 'guide_url');
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $config
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
}