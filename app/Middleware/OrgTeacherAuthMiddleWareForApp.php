<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/26
 * Time: 3:56 PM
 */

namespace App\Middleware;


use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\AppConfigModel;
use App\Models\AppVersionModel;
use App\Models\OrganizationModelForApp;
use App\Models\StudentModel;
use App\Models\StudentModelForApp;
use App\Models\TeacherModel;
use App\Services\AppVersionService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class OrgTeacherAuthMiddleWareForApp extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        $orgId = $this->container['org']['id'];
        if (empty($orgId)) {
            SimpleLogger::error(__FILE__ . __LINE__, ['empty org']);
            $result = Valid::addAppErrors([], 'invalid_org');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $token = $this->container['org_teacher_token'] ?? NULL;
        if (empty($token)) {
            if(isset($_ENV['ENV_NAME']) && $_ENV['ENV_NAME'] == 'dev') {
                $cache = ['student_id' => $_ENV['DEV_ORG_STUDENT_ID'], 'teacher_id' => $_ENV['DEV_ORG_TEACHER_ID']];
            } else {
                SimpleLogger::error(__FILE__ . __LINE__, ['empty_teacher_token']);
                $result = Valid::addAppErrors([], 'empty_teacher_token');
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
        } else {
            $cache = OrganizationModelForApp::getOrgTeacherCacheByToken($orgId, $token);
        }

        if (empty($cache)) {
            SimpleLogger::error(__FILE__ . ":" . __LINE__, ['invalid teacher token']);
            $result = Valid::addAppErrors([], 'invalid_teacher_token');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $teacherId = $cache['teacher_id'];
        $teacher = TeacherModel::getById($teacherId);
        $studentId = $cache['student_id'];
        $student = StudentModel::getById($studentId);

        SimpleLogger::info(__FILE__ . ":" . __LINE__, [
            'middleWare' => 'OrgTeacherAuthMiddleWareForApp',
            'teacher' => $teacher,
            'student' => $student
        ]);

        // 延长登录token过期时间
        StudentModelForApp::refreshStudentToken($studentId);

        $this->container['teacher'] = $teacher;
        $this->container['student'] = $student;

        // 内部审核账号，使用审核版本app也可看到所有资源
        $reviewTestUsers = AppConfigModel::get('REVIEW_TESTER');
        if (!empty($reviewTestUsers)) {
            $userMobiles = explode(',', $reviewTestUsers);
            $isOpnTester = in_array($teacher['mobile'], $userMobiles);
        } else {
            $isOpnTester = false;
        }
        $this->container['opn_is_tester'] = $isOpnTester;
        $this->container['opn_pro_ver'] = $isOpnTester ? 'tester' : $this->container['version'];
        $this->container['opn_publish'] = $isOpnTester ? 0 : 1;

        $response = $next($request, $response);

        return $response;

    }
}