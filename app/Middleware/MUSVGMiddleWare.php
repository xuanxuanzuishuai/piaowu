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
use App\Services\AIBackendService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

/**
 * 为app内H5页提供的验证middleware
 * TODO: h5页面已经添加相应header(token,version,platform),可以统一使用app的中间件验证,这个中间件不再使用
 *
 * Class MUSVGMiddleWare
 * @package App\Middleware
 * @deprecated
 */
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

        if (strpos($token, ',') !== false) {
            // 收到 "org_token,org_teacher_token" 格式
            // org_teacher_token 可能为空
            $tokens = explode(',', $token);
            $orgToken = $tokens[0];
            $teacherToken = $tokens[1];

            $orgCache = OrganizationModelForApp::getOrgCacheByToken($orgToken);
            if (!empty($orgCache)) {
                if (!empty($teacherToken)) { //org_teacher_token 不为空时获取老师学生信息
                    $teacherCache = OrganizationModelForApp::getOrgTeacherCacheByToken($orgCache['org_id'], $teacherToken);
                    if (!empty($teacherCache)) {
                        $teacher = TeacherModelForApp::getById($teacherCache['teacher_id']);
                        $this->container['teacher'] = $teacher;
                        $student = StudentModelForApp::getById($teacherCache['student_id']);
                        $this->container['student'] = $student;
                        $this->container['ai_uid'] = $student['uuid'];
                        $auth = true;
                    }

                } else { //org_teacher_token 为空时只获取机构信息
                    $org = OrganizationModelForApp::getById($orgCache['org_id']);
                    $this->container['org'] = $org;
                    $this->container['ai_uid'] = $org['id'];
                    $auth = true;
                }
            }
        } else {
            // 收到 "student_token" 格式 获取学生信息
            $studentId = StudentModelForApp::getStudentUid($token);
            if (!empty($studentId)) {
                $student = StudentModelForApp::getById($studentId);
                $this->container['student'] = $student;
                $this->container['ai_uid'] = $student['uuid'];
                $auth = true;
            }
        }

        if (strpos($token, AIBackendService::TOKEN_PRI) === 0) {
            // 收到 "student_token" 格式 获取学生信息
            $studentId = AIBackendService::validateStudentToken($token);
            if (!empty($studentId)) {
                $student = StudentModelForApp::getById($studentId);
                $this->container['student'] = $student;
                $this->container['ai_uid'] = $student['uuid'];
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