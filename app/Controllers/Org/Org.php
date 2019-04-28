<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/17
 * Time: 下午6:04
 */

namespace App\Controllers\Org;

use App\Controllers\ControllerBase;
use App\Libs\MysqlDB;
use App\Libs\Valid;
use App\Models\OrgAccountModel;
use App\Models\OrganizationModel;
use App\Models\StudentOrgModel;
use App\Models\TeacherOrgModel;
use App\Models\TeacherStudentModel;
use App\Services\OrganizationService;
use App\Services\StudentService;
use App\Services\TeacherService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Org extends ControllerBase
{
    /**
     * 添加或更新机构
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Org|Response
     */
    public function addOrUpdate(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'id',
                'type'       => 'integer',
                'error_code' => 'id_must_be_integer'
            ],
            [
                'key'        => 'name',
                'type'       => 'required',
                'error_code' => 'name_is_required'
            ],
            [
                'key'        => 'name',
                'type'       => 'lengthMax',
                'value'      => 128,
                'error_code' => 'name_max_must_elt_128'
            ],
            [
                'key'        => 'remark',
                'type'       => 'lengthMax',
                'value'      => 1024,
                'error_code' => 'remark_must_elt_1024'
            ],
            [
                'key'        => 'province_code',
                'type'       => 'required',
                'error_code' => 'province_code_is_required'
            ],
            [
                'key'        => 'province_code',
                'type'       => 'integer',
                'error_code' => 'province_code_must_be_integer'
            ],
            [
                'key'        => 'city_code',
                'type'       => 'integer',
                'error_code' => 'city_code_must_be_integer'
            ],
            [
                'key'        => 'district_code',
                'type'       => 'integer',
                'error_code' => 'district_code_must_be_integer'
            ],
            [
                'key'        => 'address',
                'type'       => 'required',
                'error_code' => 'address_is_required'
            ],
            [
                'key'        => 'address',
                'type'       => 'lengthMax',
                'value'      => 128,
                'error_code' => 'address_must_elt_128'
            ],
            [
                'key'        => 'zip_code',
                'type'       => 'integer',
                'error_code' => 'zip_code_must_be_integer'
            ],
            [
                'key'        => 'register_channel',
                'type'       => 'lengthMax',
                'value'      => 128,
                'error_code' => 'register_channel_must_elt_128'
            ],
            [
                'key'        => 'parent_id',
                'type'       => 'integer',
                'error_code' => 'parent_id_must_be_integer'
            ],
            [
                'key'        => 'start_time',
                'type'       => 'required',
                'error_code' => 'start_time_is_required'
            ],
            [
                'key'        => 'start_time',
                'type'       => 'integer',
                'error_code' => 'start_time_must_be_integer'
            ],
            [
                'key'        => 'start_time',
                'type'       => 'lengthMax',
                'value'      => 10,
                'error_code' => 'start_time_length_is_10'
            ],
            [
                'key'        => 'end_time',
                'type'       => 'required',
                'error_code' => 'end_time_is_required'
            ],
            [
                'key'        => 'end_time',
                'type'       => 'integer',
                'error_code' => 'end_time_must_be_integer'
            ],
            [
                'key'        => 'end_time',
                'type'       => 'lengthMax',
                'value'      => 10,
                'error_code' => 'end_time_length_is_10'
            ],
            [
                'key'        => 'status',
                'type'       => 'required',
                'error_code' => 'status_is_required'
            ],
            [
                'key'        => 'status',
                'type'       => 'integer',
                'error_code' => 'status_must_be_integer'
            ],
            [
                'key'        => 'license_num',
                'type'       => 'integer',
                'error_code' => 'licence_num_is_integer'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $now = time();
        $params['update_time'] = $now;
        $params['operator_id'] = $this->getEmployeeId();

        if(isset($params['id']) && !empty($params['id'])) {
            $id = $params['id'];
            unset($params['id']);
            $affectRows = OrganizationService::updateById($id, $params);
            if($affectRows == 0) {
                return $this->fail($response, 'org','update_fail');
            }

            return $this->success($response, ['last_id' => $id]);
        } else {
            $params['create_time'] = $now;
            $params['status']      = OrganizationModel::STATUS_NORMAL;
            $licenseNum            = $params['license_num'];

            unset($params['license_num']);

            $db = MysqlDB::getDB();
            $db->beginTransaction();

            //添加机构
            $lastId = OrganizationService::save($params);

            if(empty($lastId)) {
                $db->rollBack();
                return $response->withJson(Valid::addErrors([],'org','save_org_fail'));
            }

            //添加机构账号
            $max = OrgAccountModel::getMaxAccount();
            if(empty($max)) {
                $max = 10000000;
            }
            $account = $max + 1;

            $data = [
                'org_id'      => $lastId,
                'account'     => $account,
                'password'    => md5($account.$account), // 默认密码是机构账号
                'create_time' => time(),
                'status'      => OrgAccountModel::STATUS_NORMAL,
                'license_num' => $licenseNum,
            ];

            $affectRows = OrgAccountModel::insertRecord($data, false);
            if($affectRows == 0) {
                $db->rollBack();
                return $response->withJson(Valid::addErrors([], 'org', 'save_org_account_fail'));
            }

            $db->commit();

            return $this->success($response, ['last_id' => $lastId]);
        }
    }

    /**
     * 机构列表
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function list(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'page',
                'type'       => 'integer',
                'error_code' => 'page_is_integer'
            ],
            [
                'key'        => 'count',
                'type'       => 'integer',
                'error_code' => 'count_is_integer'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        list($records, $total) = OrganizationService::selectOrgList($params['page'], $params['count'], $params);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['records' => $records,'total_count' => $total]
        ]);
    }

    /**
     * 模糊搜索机构
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function fuzzySearch(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'key',
                'type'       => 'required',
                'error_code' => 'key_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $records = OrganizationService::fuzzySearch($params['key']);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $records
        ]);
    }

    /**
     * 查询指定机构详情
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
                'error_code' => 'id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $orgId = $params['id'];

        $record = OrganizationService::getInfo($orgId);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'record' => $record
            ]
        ]);
    }

    /**
     * 解绑/绑定 学生和机构
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function bindUnbindStudent(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'student_id',
                'type'       => 'required',
                'error_code' => 'student_id_is_required'
            ],
            [
                'key'        => 'bind',
                'type'       => 'required',
                'error_code' => 'bind_is_required'
            ],
            [
                'key'        => 'bind',
                'type'       => 'in',
                'value'      => [0, 1],
                'error_code' => 'bind_must_be_in_[0, 1]'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $studentId = $params['student_id'];
        $bind      = $params['bind'];
        $orgId     = $this->getEmployeeOrgId();

        $entry = StudentOrgModel::getRecord([
            'org_id'     => $orgId,
            'student_id' => $studentId,
        ]);

        if(empty($entry)) {
            return $response->withJson(Valid::addErrors([], 'student','no_bind_relation'));
        }

        //状态不同时才更新
        if($entry['status'] != $bind) {
            //解绑机构和学生时，同时也解绑学生同其机构下的老师
            if($bind == StudentOrgModel::STATUS_STOP) {
                $db = MysqlDB::getDB();
                $db->beginTransaction();

                $affectRows = StudentService::updateStatusWithOrg($orgId, $studentId, $bind);
                if($affectRows == 0) {
                    $db->rollBack();
                    return $response->withJson(Valid::addErrors([],'student','bind_unbind_fail'));
                }

                $affectRows = TeacherStudentModel::unbindTeacherStudentByStudent($orgId, $studentId);
                if(is_null($affectRows)) {
                    $db->rollBack();
                    return $response->withJson(Valid::addErrors([],'student','unbind_teacher_and_student_fail'));
                }

                $db->commit();
            } else {
                $affectRows = StudentService::updateStatusWithOrg($orgId, $studentId, $bind);
                if($affectRows == 0) {
                    return $response->withJson(Valid::addErrors([],'student','bind_unbind_fail'));
                }
            }
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [],
        ]);
    }

    /**
     * 解绑/绑定 老师和机构
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function bindUnbindTeacher(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'teacher_id',
                'type'       => 'required',
                'error_code' => 'teacher_id_is_required'
            ],
            [
                'key'        => 'bind',
                'type'       => 'required',
                'error_code' => 'bind_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $teacherId = $params['teacher_id'];
        $bind      = $params['bind'];
        $orgId     = $this->getEmployeeOrgId();

        $entry = TeacherOrgModel::getRecord([
            'org_id'     => $orgId,
            'teacher_id' => $teacherId,
        ]);

        if(empty($entry)) {
            return $response->withJson(Valid::addErrors([], 'student','no_bind_relation'));
        }

        //状态不同时才更新
        if($entry['status'] != $bind) {
            //解绑机构和老师时，同时也解绑老师同其机构下的学生
            if($bind == TeacherOrgModel::STATUS_STOP) {
                $db = MysqlDB::getDB();
                $db->beginTransaction();

                $affectRows = TeacherService::updateStatusWithOrg($orgId, $teacherId, $bind);
                if($affectRows == 0) {
                    $db->rollBack();
                    return $response->withJson(Valid::addErrors([],'student','bind_unbind_fail'));
                }

                $affectRows = TeacherStudentModel::unbindTeacherStudentByTeacher($orgId, $teacherId);
                if(is_null($affectRows)) {
                    $db->rollBack();
                    return $response->withJson(Valid::addErrors([],'student','unbind_teacher_and_student_fail'));
                }

                $db->commit();
            } else {
                $affectRows = TeacherService::updateStatusWithOrg($orgId, $teacherId, $bind);
                if($affectRows == 0) {
                    return $response->withJson(Valid::addErrors([],'teacher','bind_unbind_fail'));
                }
            }
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [],
        ]);
    }
}