<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/24
 * Time: 2:17 PM
 */

namespace App\Controllers\TeacherApp;


use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\OrganizationModelForApp;
use App\Services\AppLogServices;
use App\Services\HomeworkService;
use App\Services\OrganizationServiceForApp;
use App\Controllers\ControllerBase;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Org extends ControllerBase
{

    public function getStudents(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'teacher_id',
                'type' => 'required',
                'error_code' => 'teacher_id_is_required'
            ]
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $orgId = $this->ci['org']['id'];
        $students = OrganizationServiceForApp::getStudents($orgId, $params['teacher_id']);

        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code'=> Valid::CODE_SUCCESS,
            'data'=> ['students' => $students],
        ], StatusCode::HTTP_OK);
    }

    public function selectStudent(Request $request, Response $response)
    {

        $params = $request->getParams();
        $rules = [
            [
                'key' => 'teacher_id',
                'type' => 'required',
                'error_code' => 'teacher_id_is_required'
            ],
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'teacher_id_is_required'
            ]
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $orgId = $this->ci['org']['id'];
        $orgAccount = $this->ci['org_account'];
        //Leave below for debugging & deleting them at the end.
        //$orgId = 39;
        //$orgAccount = 10000019;

        list($errorCode, $loginData) = OrganizationServiceForApp::teacherLogin($orgId,
            $orgAccount,
            $params['teacher_id'],
            $params['student_id']
        );
        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        list($homework, $recentCollections) = HomeworkService::makeFollowUp(
            $params['teacher_id'], $params['student_id'], $this->ci['opn_pro_ver']
        );
        $loginData['homework'] = !empty($homework) ? $homework : [];
        $loginData['recent_collections'] = !empty($recentCollections) ? $recentCollections : [];

        AppLogServices::locationLog($orgId, $orgAccount, [
            'location' => $params['location'] ?? '',
            'province' => $params['province'] ?? '',
            'city' => $params['city'] ?? '',
            'district' => $params['district'] ?? '',
        ]);

        return $response->withJson([
            'code'=> Valid::CODE_SUCCESS,
            'data'=> $loginData,
        ], StatusCode::HTTP_OK);
    }

    /**
     * 老师退出上课状态
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function teacherLogout(Request $request, Response $response)
    {
        Util::unusedParam($request);
        OrganizationModelForApp::delOrgTeacherTokens($this->ci['org']['id'],
            $this->ci['org_teacher_token']);
        return $response->withJson([
            'code'=> Valid::CODE_SUCCESS,
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取机构老师列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function teacherList(Request $request, Response $response)
    {
        Util::unusedParam($request);
        $orgId = $this->ci['org']['id'];
        $orgTeachers = OrganizationServiceForApp::getTeachers($orgId);
        return $response->withJson([
            'code'=> Valid::CODE_SUCCESS,
            'data'=> $orgTeachers,
        ], StatusCode::HTTP_OK);
    }
}