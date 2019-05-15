<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/15
 * Time: 12:37 PM
 */

namespace App\Middleware;

use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\OrganizationModelForApp;
use App\Models\StudentModelForApp;
use App\Models\TeacherModelForApp;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class MUSVGMiddleWare extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        $token = $request->getHeaderLine('token');
        if (empty($token)) {
            SimpleLogger::error(__FILE__ . __LINE__, ['empty_token']);
            $result = Valid::addAppErrors([], 'empty_token');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $auth = false;

        // 收到 "org_token,org_teacher_token" 格式的token时，只取org_teacher_token
        if (strpos($token, ',') !== false) {
            $tokens = explode(',', $token);
            $token = $tokens[1];

            $orgCache = OrganizationModelForApp::getOrgCacheByToken($tokens[0]);
            if (!empty($orgCache)) {
                $teacherCache = OrganizationModelForApp::getOrgTeacherCacheByToken($orgCache['org_id'], $token);
                if (!empty($teacherCache)) {
                    $teacher = TeacherModelForApp::getById($teacherCache['teacher_id']);
                    $this->container['teacher'] = $teacher;
                    $student = StudentModelForApp::getById($teacherCache['student_id']);
                    $this->container['student'] = $student;
                    $this->container['ai_uid'] = $student['uuid'];
                    $auth = true;
                }
            }
        }

        if (!$auth) {
            $studentId = StudentModelForApp::getStudentUid($token);
            if (!empty($studentId)) {
                $student = StudentModelForApp::getById($studentId);
                $this->container['student'] = $student;
                $this->container['ai_uid'] = $student['uuid'];
                $auth = true;
            }
        }

        if (!$auth) {
            $orgCache = OrganizationModelForApp::getOrgCacheByToken($token);
            if (!empty($orgCache)) {
                $org = OrganizationModelForApp::getById($orgCache['org_id']);
                $this->container['org'] = $org;
                $this->container['ai_uid'] = $org['id'];
                $auth = true;
            }
        }

        if (!$auth) {
            $teacherCache = OrganizationModelForApp::searchOrgTeacherByToken($token);
            if (!empty($teacherCache)) {
                $teacher = TeacherModelForApp::getById($teacherCache['teacher_id']);
                $this->container['teacher'] = $teacher;
                $this->container['ai_uid'] = $teacher['uuid'];
                $auth = true;
            }
        }

        if (!$auth) {
            SimpleLogger::error(__FILE__ . ":" . __LINE__, ['invalid_token']);
            $result = Valid::addAppErrors([], 'invalid_token');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        SimpleLogger::info(__FILE__ . ":" . __LINE__, [
            'middleWare' => 'MUSVGMiddleWare',
            'org' => $this->container['org'] ?? NULL,
            'teacher' => $this->container['teacher'] ?? NULL,
            'student' => $this->container['student'] ?? NULL,
            'ai_uid' => $this->container['ai_uid'] ?? NULL,
        ]);

        $response = $next($request, $response);

        return $response;
    }
}