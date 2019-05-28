<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/17
 * Time: 下午6:04
 */

namespace App\Controllers\Org;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Dict;
use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\ChannelModel;
use App\Models\EmployeeModel;
use App\Models\OrgAccountModel;
use App\Models\OrganizationModel;
use App\Models\QRCodeModel;
use App\Models\StudentOrgModel;
use App\Models\TeacherOrgModel;
use App\Models\TeacherStudentModel;
use App\Services\EmployeeService;
use App\Services\OrganizationService;
use App\Services\QRCodeService;
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
                'type'       => 'length',
                'value'      => 6,
                'error_code' => 'zip_code_length_is_6'
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
            [
                'key'        => 'license_num',
                'type'       => 'required',
                'error_code' => 'licence_num_is_required'
            ],
        ];

        $params = $request->getParams();

        //添加机构时候，自动添加一个角色是校长的employee,需要检查校长的手机号登录名等信息
        if(empty($params['id'])) {
            $rules = array_merge($rules, [
                [
                    'key'        => 'login_name',
                    'type'       => 'required',
                    'error_code' => 'login_name_is_required'
                ],
                [
                    'key'        => 'login_name',
                    'type'       => 'regex',
                    'value'      => '/^[a-z_0-9]+$/',
                    'error_code' => 'login_name_format_error'
                ],
                [
                    'key'        => 'principal_name',
                    'type'       => 'required',
                    'error_code' => 'principal_name_is_required'
                ],
                [
                    'key'        => 'mobile',
                    'type'       => 'required',
                    'error_code' => 'mobile_is_required'
                ],
                [
                    'key'        => 'mobile',
                    'type'       => 'regex',
                    'value'      => Constants::MOBILE_REGEX,
                    'error_code' => 'mobile_format_error'
                ]
            ]);
        }

        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        if(!empty($params['parent_id'])) {
            $parent = OrganizationModel::getById($params['parent_id']);
            if(empty($parent)) {
                return $response->withJson(Valid::addErrors([], 'org', 'parent_org_not_exist'));
            }
        }

        $now = time();
        $params['update_time'] = $now;
        $params['operator_id'] = $this->getEmployeeId();

        if(isset($params['id']) && !empty($params['id'])) {
            $id = $params['id'];

            //检查机构名是否已存在
            $exist = OrganizationModel::getRecord(['name' => $params['name'], 'id[!]' => $id]);
            if(!empty($exist)) {
                return $response->withJson(Valid::addErrors([], 'org', 'org_has_exist'));
            }

            $data = [
                'name'             => $params['name'],
                'remark'           => $params['remark'] ?? '',
                'province_code'    => $params['province_code'],
                'city_code'        => $params['city_code'],
                'district_code'    => $params['district_code'],
                'address'          => $params['address'],
                'zip_code'         => $params['zip_code'] ?? '',
                'register_channel' => $params['register_channel'],
                'parent_id'        => $params['parent_id'],
                'start_time'       => $params['start_time'] ?? 0,
                'end_time'         => $params['end_time'] ?? 0,
                'status'           => $params['status'],
                'update_time'      => $now,
            ];

            $affectRows = OrganizationService::updateById($id, $data);

            if($affectRows == 0) {
                return $this->fail($response, 'org','update_fail');
            }

            return $this->success($response, ['last_id' => $id]);
        } else {
            //检查机构名是否已存在
            $exist = OrganizationModel::getRecord(['name' => $params['name']], [], false);
            if(!empty($exist)) {
                return $response->withJson(Valid::addErrors([], 'org', 'org_has_exist'));
            }

            $principalRoleId = Dict::getPrincipalRoleId();
            if(empty($principalRoleId)) {
                return $response->withJson(Valid::addErrors([], 'org', 'principal_role_id_is_empty'));
            }

            //判断手机号是否存在
            $employee = EmployeeModel::getRecord(['mobile' => $params['mobile']], [], false);
            if(!empty($employee)) {
                return $response->withJson(Valid::addErrors([], 'org', 'mobile_has_exist'));
            }

            $now = time();

            $db = MysqlDB::getDB();
            $db->beginTransaction();

            $orgData = [
                'name'             => $params['name'],
                'remark'           => $params['remark'] ?? '',
                'province_code'    => $params['province_code'],
                'city_code'        => $params['city_code'],
                'district_code'    => $params['district_code'],
                'address'          => $params['address'],
                'zip_code'         => $params['zip_code'] ?? '',
                'register_channel' => $params['register_channel'],
                'parent_id'        => $params['parent_id'],
                'start_time'       => $params['start_time'] ?? 0,
                'end_time'         => $params['end_time'] ?? 0,
                'operator_id'      => $this->getEmployeeId(),
                'status'           => OrganizationModel::STATUS_NORMAL,
                'create_time'      => $now,
            ];
            //添加机构
            $lastId = OrganizationService::save($orgData);

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

            $accountData = [
                'org_id'      => $lastId,
                'account'     => $account,
                'password'    => md5($account.$account), // 默认密码是机构账号
                'create_time' => $now,
                'status'      => OrgAccountModel::STATUS_NORMAL,
                'license_num' => $params['license_num'],
            ];

            $affectRows = OrgAccountModel::insertRecord($accountData, false);
            if($affectRows == 0) {
                $db->rollBack();
                return $response->withJson(Valid::addErrors([], 'org', 'save_org_account_fail'));
            }

            //添加employee
            $employeeData = [
                'role_id'    => $principalRoleId,
                'login_name' => Util::makeOrgLoginName($lastId, $params['login_name']),
                'name'       => $params['principal_name'],
                'mobile'     => $params['mobile'],
                'org_id'     => $lastId,
                'pwd'        => "Org{$lastId}_{$params['login_name']}", //校长默认密码是Org+ID+下划线+登录名
                'status'     => EmployeeModel::STATUS_NORMAL,
            ];

            $employeeIdOrErr = EmployeeService::insertOrUpdateEmployee($employeeData);

            if(isset($employeeIdOrErr['data']['errors']['login_name'])) {
                $db->rollBack();
                return $response->withJson(Valid::addErrors([], 'org', 'mobile_has_exist'));
            }
            if (is_array($employeeIdOrErr)) {
                $db->rollBack();
                return $response->withJson(Valid::addErrors([], 'org', 'login_name_conflict'));
            }
            if (empty($employeeIdOrErr)) {
                $db->rollBack();
                return $response->withJson(Valid::addErrors([], 'org', 'save_employee_fail'));
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

        //获取校长role_id
        $params['role_id'] = Dict::getPrincipalRoleId();

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

    public function qrcode(Request $request, Response $response, $args)
    {
        global $orgId;

        $teacher = QRCodeService::getOrgTeacherBindQR($orgId);
        $student = QRCodeService::getOrgStudentBindQR($orgId);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'teacher' => $teacher['qr_image'],
                'student' => $student['qr_image'],
            ]
        ]);
    }

    public function refereeQrcode(Request $request, Response $response, $args)
    {
        global $orgId;
        $employeeId = $this->ci['employee']['id'];

        $referee = QRCodeService::getOrgStudentBindRefereeQR($orgId, QRCodeModel::REFEREE_TYPE_EMPLOYEE, $employeeId);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'referee' => $referee['qr_image'],
            ]
        ]);
    }

    public function channelList(Request $request, Response $response, $args)
    {
        $list = ChannelModel::getRecords(['status' => ChannelModel::STATUS_NORMAL], [], false);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $list,
        ]);
    }
}