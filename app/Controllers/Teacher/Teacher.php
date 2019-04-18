<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 11:03
 */

namespace App\Controllers\Teacher;


use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\TeacherModel;
use App\Libs\ResponseError;
use App\Services\StudentService;
use App\Services\TeacherOrgService;
use App\Services\TeacherService;
use App\Services\TeacherStudentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Teacher extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function detail(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'teacher_id_is_required'
            ],
            [
                'key' => 'id',
                'type' => 'integer',
                'error_code' => 'teacher_id_must_be_integer'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        //获取老师详细信息
        $teacher_info = TeacherService::getTeacherInfoByID($params['id']);
        if (isset($teacher_info['code']) && $teacher_info['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($teacher_info, StatusCode::HTTP_OK);
        }
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'teacher_info' => $teacher_info
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function modify(Request $request, Response $response, $args)
    {

        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'teacher_id_is_required'
            ],
            [
                'key' => 'id',
                'type' => 'integer',
                'error_code' => 'teacher_id_must_be_integer'
            ],
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'teacher_name_is_required'
            ],
            [
                'key' => 'name',
                'type' => 'lengthMax',
                'value' => 10,
                'error_code' => 'teacher_name_format_is_error'
            ],
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'teacher_mobile_is_required'
            ],
            [
                'key' => 'mobile',
                'type' => 'regex',
                'value' => Constants::MOBILE_REGEX,
                'error_code' => 'teacher_mobile_format_is_error'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        if (empty($params['status'])) {
            $params['status'] = TeacherModel::ENTRY_ON;
        }
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $res = TeacherService::insertOrUpdateTeacher($params, $this->ci['employee']);
        if ($res['code'] != Valid::CODE_SUCCESS) {
            $db->rollBack();
            return $response->withJson($res, StatusCode::HTTP_OK);
        }
        $db->commit();

        return $response->withJson($res, StatusCode::HTTP_OK);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function add(Request $request, Response $response, $args)
    {

        $rules = [
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'teacher_name_is_required'
            ],
            [
                'key' => 'name',
                'type' => 'lengthMax',
                'value' => 10,
                'error_code' => 'teacher_name_format_is_error'
            ],
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'teacher_mobile_is_required'
            ],
            [
                'key' => 'mobile',
                'type' => 'regex',
                'value' => Constants::MOBILE_REGEX,
                'error_code' => 'teacher_mobile_format_is_error'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        if (empty($params['status'])) {
            $params['status'] = TeacherModel::ENTRY_ON;
        }
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $res = TeacherService::insertOrUpdateTeacher($params, $this->ci['employee']);
        if ($res['code'] != Valid::CODE_SUCCESS) {
            $db->rollBack();
            return $response->withJson($res, StatusCode::HTTP_OK);
        }
        $db->commit();

        return $response->withJson($res, StatusCode::HTTP_OK);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function list(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        list($page, $count) = Util::formatPageCount($params);
        $orgId = $this->getEmployeeOrgId();

        list($teachers, $totalCount) = TeacherService::getList($orgId, $page, $count, $params);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'teachers' => $teachers,
                'total_count' => $totalCount[0]['totalCount']
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 管理员查看所有老师，或指定机构下老师
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function listByOrg(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        list($page, $count) = Util::formatPageCount($params);
        $orgId = $params['org_id'] ?? null;

        list($teachers, $totalCount) = TeacherService::getList($orgId, $page, $count, $params);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'teachers' => $teachers,
                'total_count' => $totalCount[0]['totalCount']
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 绑定学生，机构操作
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Teacher|Response
     */
    public function bindStudent(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'teacher_id',
                'type'       => 'required',
                'error_code' => 'teacher_is_required'
            ],
            [
                'key'        => 'student_id',
                'type'       => 'required',
                'error_code' => 'student_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $teacherId = $params['teacher_id'];
        $studentId = $params['student_id'];
        $orgId     = $this->getEmployeeOrgId();

        //检查当前机构是否绑定老师
        $relation = TeacherOrgService::getTeacherByOrgAndId($orgId, $teacherId);
        if(empty($relation)) {
            return $response->withJson(Valid::addErrors([],'teacher','have_no_binding_to_teacher'));
        }

        //检查当前机构是否绑定学生
        $student = StudentService::getOrgStudent($orgId, $studentId);
        if(empty($student)) {
            return $response->withJson(Valid::addErrors([],'teacher','have_no_binding_to_student'));
        }

        //绑定失败返回错误，成功返回id
        $errOrLastId = TeacherStudentService::bindStudent($orgId, $teacherId, $studentId);
        if($errOrLastId instanceof ResponseError) {
            return $response->withJson(Valid::addErrors([],'teacher',$errOrLastId->getErrorMsg()));
        }

        return $this->success($response, ['last_id' => $errOrLastId]);
    }

    /**
     * 解绑学生，机构操作
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function unbindStudent(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'teacher_id',
                'type'       => 'required',
                'error_code' => 'teacher_is_required'
            ],
            [
                'key'        => 'student_id',
                'type'       => 'required',
                'error_code' => 'student_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $teacherId = $params['teacher_id'];
        $studentId = $params['student_id'];
        $orgId     = $this->getEmployeeOrgId();

        $affectRows = TeacherStudentService::unbindStudent($orgId, $teacherId, $studentId);
        if($affectRows == 0) {
            return $response->withJson(Valid::addErrors([],'teacher','unbind_fail'));
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS
        ]);
    }
}