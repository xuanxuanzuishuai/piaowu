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
use App\Models\StudentOrgModel;
use App\Models\TeacherModel;
use App\Libs\ResponseError;
use App\Models\TeacherOrgModel;
use App\Models\TeacherStudentModel;
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
                'key'        => 'id',
                'type'       => 'required',
                'error_code' => 'teacher_id_is_required'
            ],
            [
                'key'        => 'id',
                'type'       => 'integer',
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
     * 查看机构下老师详情
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function info(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'id',
                'type'       => 'required',
                'error_code' => 'teacher_id_is_required'
            ],
            [
                'key'        => 'id',
                'type'       => 'integer',
                'error_code' => 'teacher_id_must_be_integer'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        global $orgId;

        $teacherId = $params['id'];

        $teacher = TeacherService::getOrgTeacherById($orgId, $teacherId);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'teacher' => $teacher
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
                'key'        => 'id',
                'type'       => 'required',
                'error_code' => 'teacher_id_is_required'
            ],
            [
                'key'        => 'id',
                'type'       => 'integer',
                'error_code' => 'teacher_id_must_be_integer'
            ],
            [
                'key'        => 'name',
                'type'       => 'required',
                'error_code' => 'teacher_name_is_required'
            ],
            [
                'key'        => 'name',
                'type'       => 'lengthMax',
                'value'      => 10,
                'error_code' => 'teacher_name_format_is_error'
            ],
            [
                'key'        => 'mobile',
                'type'       => 'required',
                'error_code' => 'teacher_mobile_is_required'
            ],
            [
                'key'        => 'mobile',
                'type'       => 'regex',
                'value'      => Constants::MOBILE_REGEX,
                'error_code' => 'teacher_mobile_format_is_error'
            ],
            [
                'key'        => 'gender',
                'type'       => 'required',
                'error_code' => 'gender_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $teacherId = $params['id'];
        unset($params['id']);
        $orgId = $this->getEmployeeOrgId();

        //查询老师是否和机构有绑定关系（或曾经有绑定关系）
        $teacher = TeacherService::getOrgTeacherById($orgId, $teacherId);
        if(empty($teacher)) {
            return $response->withJson(Valid::addErrors([], 'teacher', 'have_not_binding_with_org'));
        }

        //手机号不能编辑，一旦添加不能修改
        if($teacher['mobile'] != $params['mobile']) {
            return $response->withJson(Valid::addErrors([],'teacher','mobile_can_not_different'));
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $res = TeacherService::updateTeacher($teacherId,$params);
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
                'key'        => 'name',
                'type'       => 'required',
                'error_code' => 'teacher_name_is_required'
            ],
            [
                'key'        => 'name',
                'type'       => 'lengthMax',
                'value'      => 10,
                'error_code' => 'teacher_name_format_is_error'
            ],
            [
                'key'        => 'mobile',
                'type'       => 'required',
                'error_code' => 'teacher_mobile_is_required'
            ],
            [
                'key'        => 'mobile',
                'type'       => 'regex',
                'value'      => Constants::MOBILE_REGEX,
                'error_code' => 'teacher_mobile_format_is_error'
            ],
            [
                'key'        => 'gender',
                'type'       => 'required',
                'error_code' => 'gender_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $params['status'] = TeacherModel::STATUS_NORMAL;
        $params['recruit_status'] = TeacherModel::ENTRY_ON;

        $orgId = $this->getEmployeeOrgId();

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        //新增老师
        $res = TeacherService::saveAndUpdateTeacher($params);
        if ($res['code'] != Valid::CODE_SUCCESS) {
            $db->rollBack();
            return $response->withJson($res, StatusCode::HTTP_OK);
        }

        //绑定机构
        $errOrId = TeacherService::bindOrg($orgId, $res['data']['id']);
        if($errOrId instanceof ResponseError) {
            $db->rollBack();
            return $response->withJson(Valid::addErrors([],'teacher',$errOrId->getErrorMsg()));
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
        $relation = TeacherOrgService::getTeacherByOrgAndId($orgId, $teacherId, TeacherOrgModel::STATUS_NORMAL);
        if(empty($relation)) {
            return $response->withJson(Valid::addErrors([],'teacher','have_no_binding_to_teacher'));
        }

        //检查当前机构是否绑定学生(正常绑定，不是曾经绑定)
        $student = StudentService::getOrgStudent($orgId, $studentId, StudentOrgModel::STATUS_NORMAL);
        if(empty($student)) {
            return $response->withJson(Valid::addErrors([],'teacher','have_no_binding_to_student'));
        }

        //学生是否已经绑定其他老师
        $entry = TeacherStudentModel::getRecordExceptTeacher($orgId, $studentId, $teacherId);
        if(!empty($entry)) {
            return $response->withJson(Valid::addErrors([], 'teacher','have_bind_other_teacher'));
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

    /**
     * 模糊查询老师，根据姓名或手机号
     * 姓名模糊匹配，手机号等于匹配
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function fuzzySearch(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'key', //手机号或姓名
                'type'       => 'required',
                'error_code' => 'key_is_required'
            ],
        ];

        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $key = $params['key'];
        global $orgId;

        $records = TeacherModel::fuzzySearch($orgId, $key);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $records
        ]);
    }
}