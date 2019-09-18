<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/6/27
 * Time: 下午7:52
 */

namespace App\Middleware;

use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\StudentModelForApp;
use App\Services\FlagsService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class StudentAuthCheckMiddleWareForApp extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        $token = $this->container['token'] ?? NULL;
        if (empty($token)) {
            if(isset($_ENV['ENV_NAME']) && $_ENV['ENV_NAME'] == 'dev') {
                $studentId = $_ENV['DEV_USER_ID'];
            } else {
                SimpleLogger::error(__FILE__ . __LINE__, ['empty token']);
                $result = Valid::addAppErrors([], 'empty_token');
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
        } else {
            $studentId = StudentModelForApp::getStudentUid($token);
        }

        if (empty($studentId)) {
            SimpleLogger::error(__FILE__ . ":" . __LINE__, ['invalid token']);
            $result = Valid::addAppErrors([], 'invalid_token');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $student = StudentModelForApp::getStudentInfo($studentId, null);

        // 延长登录token过期时间
        StudentModelForApp::refreshStudentToken($studentId);

        $this->container['student'] = $student;
        $student['version'] = $this->container['version'];
        $student['platform'] = $this->container['platform'];

        $reviewFlagId = DictConstants::get(DictConstants::FLAG_ID, 'app_review');
        $reviewFlag = $this->container['flags'][$reviewFlagId];
        if (!$reviewFlag) {
            // 如果没有审核版本标记，在验证用户身份后再次检查用户是否有审核标签
            $reviewFlag = FlagsService::hasFlag($student, $reviewFlagId);
            $this->addFlag($reviewFlagId, $reviewFlag);
        }

        // 内部资源测试账号，可看到所有资源，包括未发布的
        $resFreeFlagId = DictConstants::get(DictConstants::FLAG_ID, 'res_free');
        $resFreeFlag = FlagsService::hasFlag($student, $resFreeFlagId);
        $this->addFlag($resFreeFlagId, $resFreeFlag);

        $this->container['opn_pro_ver'] = $resFreeFlag ? 'tester' : $this->container['version'];
        $this->container['opn_auditing'] = $this->container['flags'][$reviewFlagId] ? 1 : 0;
        $this->container['opn_publish'] = $resFreeFlag ? 0 : 1;

        SimpleLogger::info(__FILE__ . ":" . __LINE__, [
            'middleWare' => 'StudentAuthCheckMiddleWareForApp',
            'student' => $student,
            'flags' => $this->container['flags'],
            'opn_pro_ver' => $this->container['opn_pro_ver'],
            'opn_auditing' => $this->container['opn_auditing'],
            'opn_publish' => $this->container['opn_publish'],
        ]);

        $response = $next($request, $response);

        return $response;

    }
}