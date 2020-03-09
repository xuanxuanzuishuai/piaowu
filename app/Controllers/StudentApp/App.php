<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/18
 * Time: 8:51 PM
 */

namespace App\Controllers\StudentApp;


use App\Controllers\ControllerBase;
use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\HttpHelper;
use App\Libs\PandaCRM;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\AppVersionModel;
use App\Models\FeedbackModel;
use App\Models\StudentModelForApp;
use App\Services\AppVersionService;
use App\Services\BannerService;
use App\Services\FlagsService;
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
        $config['ai_host'] = DictConstants::get(DictConstants::APP_CONFIG_COMMON, 'ai_host');
        $config['new_ai_host'] = DictConstants::get(DictConstants::APP_CONFIG_COMMON, 'new_ai_host');

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
            'lesson_id' => $params['lesson_id'],
            'client_info' => $params['client_info'],
            'tags' => Util::arrayToBitmap(explode(',', $params['tags'])),
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


    /**
     * 获取OSS上传签名
     *
     * 完整上传路径分3段
     * env_name/type_name/custom_name
     *
     * env_name: dev|test|pre|prod
     * type_name: img(机构后台自主上传的图片)|teacher_note(老师端保存笔记)|dynamic_midi(学生端动态演奏midi)
     * custom_name: 客户端自己定义的名字，可以添加自定义的目录层级方便管理
     *
     * dev/img/course_cover/abc123.jpg
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getSignature(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'type',
                'type' => 'required',
                'error_code' => 'oss_sign_type_invalid'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $ossConfig = DictConstants::getSet(DictConstants::ALI_OSS_CONFIG);
        $dir = AliOSS::getDirByType($params['type']);
        if (empty($dir)) {
            $result = Valid::addAppErrors([], 'oss_sign_type_invalid');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        if (!empty($this->ci['student']['id'])) {
            $dir = $dir . 'uid_' . $this->ci['student']['id'];
        }
        $sessionName = time();

        list($errorCode, $ret) = AliOSS::getAccessCredential($ossConfig['bucket'],
            $ossConfig['endpoint'],
            $ossConfig['record_file_arn'],
            $dir,
            $sessionName);

        $ret['credentials'] = $ret['Credentials'];
        unset($ret['Credentials']);

        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $ret
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取曲谱引擎
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function engine(Request $request, Response $response)
    {
        Util::unusedParam($request);

        $platformId = AppVersionService::getPlatformId($this->ci['platform']);
        $engine = AppVersionService::getEngine(AppVersionModel::APP_TYPE_STUDENT, $platformId, $this->ci['version']);
        if (!empty($engine['url'])) {
            $engine['url'] = AliOSS::replaceCdnDomain($engine['url']);
        }

        return HttpHelper::buildResponse($response, ['engine' => $engine]);
    }

    /**
     * 获取banner
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function banner(/** @noinspection PhpUnusedParameterInspection */ Request $request, Response $response)
    {
        $student = StudentModelForApp::getById($this->ci['student']['id']);
        $flags = FlagsService::flagsToArray($student['flags']);

        // app审核标记
        $reviewFlagId = DictConstants::get(DictConstants::FLAG_ID, 'app_review');
        if (!in_array($reviewFlagId, $flags)) {
            $object = $student;
            $object['platform'] = $this->ci['platform'];
            $object['version'] = $this->ci['version'];
            $isReviewVersion = FlagsService::hasFlag($object, $reviewFlagId);
            if ($isReviewVersion) {
                $flags[] = (int)$reviewFlagId;
            }
        } else {
            $isReviewVersion = true;
        }

        // 审核版本返回空
        if ($isReviewVersion) {
            $banner = [];
        } else {
            $banner = BannerService::getStudentBanner($this->ci['student']['id']);
        }

        return HttpHelper::buildResponse($response, ['banner' => $banner]);
    }
}