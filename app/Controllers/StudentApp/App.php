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
use App\Libs\PandaCRM;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\AppVersionModel;
use App\Models\FeedbackModel;
use App\Services\AppVersionService;
use App\Services\StudentServiceForApp;
use App\Services\TrackService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class App extends ControllerBase
{
    public function version(Request $request, Response $response)
    {
        Util::unusedParam($request);

        $platformId = AppVersionService::getPlatformId($this->ci['platform']);
        $reviewFlagId = DictConstants::get(DictConstants::FLAG_ID, 'app_review');

        if ($this->ci['flags'][$reviewFlagId]) {
            $lastVersion = AppVersionService::defaultLastVersion($this->ci['version']);
            $hotfix = AppVersionService::defaultHotfixConfig($this->ci['version']);
        } else {
            $lastVersion = AppVersionService::getLastVersion(AppVersionModel::APP_TYPE_STUDENT, $platformId, $this->ci['version']);
            $hotfix = AppVersionService::getHotfixConfig(AppVersionModel::APP_TYPE_STUDENT, $platformId, $this->ci['version']);
        }

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
        $params = $request->getParams();

        $studentAppConfigs = DictConstants::getSet(DictConstants::APP_CONFIG_STUDENT);

        $config = [];
        $config['ai_host'] = DictConstants::get(DictConstants::APP_CONFIG_COMMON, 'ai_host');
        $config['policy_url'] = $studentAppConfigs['policy_url'];
        $config['sub_info_count'] = (int)$studentAppConfigs['sub_info_count'];
        $config['tmall_2680'] = $studentAppConfigs['tmall_2680'];
        $config['tmall_599'] = $studentAppConfigs['tmall_599'];
        $config['pay_url'] = $studentAppConfigs['pay_url'];
        $config['share_url'] = $studentAppConfigs['share_url'];
        $config['trial_duration'] = (int)$studentAppConfigs['trial_duration'];
        $config['ai_adjust_db'] = (int)$studentAppConfigs['ai_adjust_db'];
        $config['device_check'] = (int)$studentAppConfigs['device_check'];
        $config['exam_enable'] = (int)$studentAppConfigs['exam_enable'];
        $config['exam_url'] = $studentAppConfigs['exam_url'];

        $reviewFlagId = DictConstants::get(DictConstants::FLAG_ID, 'app_review');
        if ($this->ci['flags'][$reviewFlagId]) {
            $config['guide_url'] = $studentAppConfigs['review_guide_url'];
            $config['share'] = 0;
        } else {
            $config['guide_url'] = $studentAppConfigs['guide_url'];
            $config['share'] = (int)$studentAppConfigs['share'];
        }

        $platformId = TrackService::getPlatformId($this->ci['platform']);
        $trackParams = TrackService::getPlatformParams($platformId, $params);
        if (!empty($trackParams)) {
            $trackParams['platform'] = $platformId;
            $trackData = TrackService::trackEvent(TrackService::TRACK_EVENT_ACTIVE, $trackParams);
            $config['ad_active'] = $trackData['complete'] ? 1 : 0;
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
                'error_code' => 'content_is_required'
            ],
            [
                'key' => 'content_type',
                'type' => 'in',
                'value' => [FeedbackModel::CONTENT_SCORE_ERROR],
                'error_code' => 'content_type_invalid'
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
            'content_type' => $params['content_type'],
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

    public function action(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'type',
                'type' => 'required',
                'error_code' => 'type_is_required'
            ],
            [
                'key' => 'type',
                'type' => 'in',
                'value' => [StudentServiceForApp::ACTION_READ_SUB_INFO],
                'error_code' => 'type_is_invalid'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        list($errorCode, $ret) = StudentServiceForApp::action($this->ci['student']['id'], $params['type']);

        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                $params['type'] => $ret,
            ]
        ], StatusCode::HTTP_OK);

    }

    public function setNickname(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'nickname',
                'type' => 'required',
                'error_code' => 'nickname_is_required'
            ],
            [
                'key' => 'nickname',
                'type' => 'regex',
                'value' => "/^[\x{4e00}-\x{9fa5}A-Za-z0-9_]{1,10}$/u",
                'error_code' => 'nickname_is_invalid'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $errorCode = StudentServiceForApp::setNickname($this->ci['student']['id'], $params['nickname']);

        if ($errorCode) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => []
        ], StatusCode::HTTP_OK);
    }

    public function leadsCheck(Request $request, Response $response)
    {
        Util::unusedParam($request);

        $crm = new PandaCRM();
        $isLeads = $crm->leadsCheck($this->ci['student']['mobile']);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'is_leads' => $isLeads ? 1 : 0
            ]
        ], StatusCode::HTTP_OK);
    }
}