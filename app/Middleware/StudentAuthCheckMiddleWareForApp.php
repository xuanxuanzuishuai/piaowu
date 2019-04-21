<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/6/27
 * Time: 下午7:52
 */

namespace App\Middleware;

use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\AppConfigModel;
use App\Models\StudentModelForApp;
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
        $user = StudentModelForApp::getStudentInfo($studentId, null);

        SimpleLogger::info(__FILE__ . ":" . __LINE__, [
            'middleWare' => 'UserAuthCheckMiddleWare',
            'user' => $user
        ]);

        // 延长登录token过期时间
        StudentModelForApp::refreshStudentToken($studentId);

        $this->container['student'] = $user;

        $reviewVersion = AppConfigModel::get(AppConfigModel::REVIEW_VERSION);
        $isReviewVersion = ($this->container['platform'] == 'android') && ($reviewVersion == $this->container['version']);
        $this->container['is_review_version'] = $isReviewVersion;

        // 内部审核账号，使用审核版本app也可看到所有资源
        $reviewTestUsers = AppConfigModel::get('REVIEW_TESTER');
        if (!empty($reviewTestUsers)) {
            $userMobiles = explode(',', $reviewTestUsers);
            $isOpnTester = in_array($user['mobile'], $userMobiles);
            $this->container['opn_is_tester'] = $isOpnTester;

            if ($isOpnTester) {
                $this->container['opn_auditing'] = 0;
                $this->container['opn_publish'] = 0;
            } else {
                $this->container['opn_auditing'] = $isReviewVersion ? 1 : 0;
                $this->container['opn_publish'] = 1;
            }

        } else {
            $this->container['opn_is_tester'] = false;
        }

        $response = $next($request, $response);

        return $response;

    }
}