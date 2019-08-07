<?php
/**
 * Created by PhpStorm.
 * User: dahua
 * Date: 2019/4/22
 * Time: 15:28
 */

namespace App\Controllers\TeacherApp;

use App\Controllers\ControllerBase;
use App\Libs\AliOSS;
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
        $lastVersion = AppVersionService::getLastVersion(AppVersionModel::APP_TYPE_TEACHER, $platformId, $this->ci['version']);
        $hotfix = AppVersionService::getHotfixConfig(AppVersionModel::APP_TYPE_TEACHER, $platformId, $this->ci['version']);

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
        $config['policy_url'] = DictConstants::get(DictConstants::APP_CONFIG_TEACHER, 'policy_url');

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

        if (!empty($this->ci['teacher'])) {
            $userId = $this->ci['teacher']['id'];
            $userType = FeedbackModel::TYPE_TEACHER;
        } else {
            $userId = $this->ci['org']['id'];
            $userType = FeedbackModel::TYPE_ORG;
        }
        $data = [
            'user_type' => $userType,
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

    public function heartBeat(Request $request, Response $response)
    {
        Util::unusedParam($request);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'teacher_id' => $this->ci['teacher']['id'],
                'student_ids' => $this->ci['student_ids'],
            ],
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

        $sessionName = $this->ci['org']['id'] . ',' . ($this->ci['teacher']['id'] ?? '');

        list($errorCode, $ret) = AliOSS::getAccessCredential($ossConfig['bucket'],
            $ossConfig['endpoint'],
            $ossConfig['record_file_arn'],
            $dir,
            $sessionName);

        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $ret
        ], StatusCode::HTTP_OK);
    }
}