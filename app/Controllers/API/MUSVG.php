<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/13
 * Time: 6:47 PM
 */

namespace App\Controllers\API;


use App\Controllers\ControllerBase;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\OrganizationModelForApp;
use App\Models\StudentModelForApp;
use App\Models\TeacherModelForApp;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class MUSVG extends ControllerBase
{
    /**
     * 验证musvg传给测评服务的token
     * 学生app传学生token
     * 老师app未选择老师时传机构token
     * 老师app选择老师后传老师token
     * 依次验证3种情况，为老师或学生token时返回uuid，为机构时返回机构account，否则返回错误
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getUserId(Request $request, Response $response)
    {
        $token = $request->getHeaderLine('token');
        if (empty($token)) {
            SimpleLogger::error(__FILE__ . __LINE__, ['empty_token']);
            $result = Valid::addAppErrors([], 'empty_token');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        // 收到 "org_token,org_teacher_token" 格式的token时，只取org_teacher_token
        if (strpos($token, ',') !== false) {
            $tokens = explode(',', $token);
            $token = $tokens[1];
        }

        $uid = null;

        if (empty($uid)) {
            $studentId = StudentModelForApp::getStudentUid($token);
            if (!empty($studentId)) {
                $student = StudentModelForApp::getById($studentId);
                $uid = $student['uuid'];
            }
        }

        if (empty($uid)) {
            $orgCache = OrganizationModelForApp::getOrgCacheByToken($token);
            if (!empty($orgCache)) {
                $uid = $orgCache['account'];
            }
        }

        if (empty($uid)) {
            $teacherCache = OrganizationModelForApp::searchOrgTeacherByToken($token);
            if (!empty($teacherCache)) {
                $teacher = TeacherModelForApp::getById($teacherCache['teacher_id']);
                $uid = $teacher['uuid'];
            }
        }

        if (empty($uid)) {
            SimpleLogger::error(__FILE__ . ":" . __LINE__, ['invalid_token']);
            $result = Valid::addAppErrors([], 'invalid_token');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code'=> Valid::CODE_SUCCESS,
            'user_id' => $uid,
        ], StatusCode::HTTP_OK);
    }
}