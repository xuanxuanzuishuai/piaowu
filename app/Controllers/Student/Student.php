<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/4
 * Time: 7:42 PM
 *
 * 客户数据相关功能
 */

namespace App\Controllers\Student;

use App\Controllers\ControllerBase;
use App\Libs\MysqlDB;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\EmployeeModel;
use App\Models\StudentAppModel;
use App\Models\StudentModel;
use App\Services\ChannelService;
use App\Services\StudentAppService;
use App\Services\StudentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;


/**
 * 搜索客户
 */
class Student extends ControllerBase
{

    /**
     * 获取学员列表
     * @param Request $request
     * @param Response $response
     * @param $argv
     * @return Response
     */
    public function list(Request $request, Response $response, $argv)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'page',
                'type'       => 'required',
                'error_code' => 'page_is_required',
            ],
            [
                'key'        => 'count',
                'type'       => 'required',
                'error_code' => 'count_is_required',
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }

        $orgId = $this->getEmployeeOrgId();

        list($data, $total) = StudentService::selectStudentByOrg($orgId,$params['page'], $params['count'], $params);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'students'    => $data,
                'total_count' => $total
            ],
        ]);
    }

    public function listByOrg(Request $request, Response $response, $argv)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'page',
                'type'       => 'required',
                'error_code' => 'page_is_required',
            ],
            [
                'key'        => 'count',
                'type'       => 'required',
                'error_code' => 'count_is_required',
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }

        $orgId = $params['org_id'] ?? null;

        list($data, $total) = StudentService::selectStudentByOrg($orgId,$params['page'], $params['count'], $params);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'students'    => $data,
                'total_count' => $total
            ],
        ]);
    }

    /**
     * 获取学生渠道
     * @param Request $request
     * @param Response $response
     * @param $argv
     * @return Response
     */
    public function getSChannels(Request $request, Response $response, $argv)
    {
        $parent_id = $request->getParam('parent_id', 0);

        $channels = ChannelService::getChannels($parent_id);
        return $response->withJson([
            'code' => 0,
            'data' => $channels
        ]);
    }

    /**
     * 获取学生详情
     * @param Request $request
     * @param Response $response
     * @param $argv
     * @return Response
     */
    public function detail(Request $request, Response $response, $argv)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required',
            ],
            [
                'key' => 'student_id',
                'type' => 'integer',
                'error_code' => 'student_id_must_be_integer'
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }

        $data = StudentService::fetchStudentDetail($params['student_id'], false);
        return $response->withJson([
            'code' => 0,
            'data' => $data
        ], 200);
    }

    /**
     * 修改学生信息
     * @param Request $request
     * @param Response $response
     * @param $argv
     * @return Response
     */
    public function modify(Request $request, Response $response, $argv)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required',
            ],
            [
                'key' => 'gender',
                'type' => 'required',
                'error_code' => 'gender_is_required',
            ],
            [
                'key' => 'birthday',
                'type' => 'required',
                'error_code' => 'birthday_is_required',
            ],
            [
                'key' => 'birthday',
                'type' => 'numeric',
                'error_code' => 'birthday_must_be_numeric',
            ],
            [
                'key' => 'instruments',
                'type' => 'required',
                'error_code' => 'instruments_is_required',
            ]
        ];

        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }

        if (isset($params['relations'])) {
            //如果没填关联人 则置空
            if (count($params['relations']) == 1) {
                $row = current($params['relations']);
                if (empty($row['title']) && empty($row['mobile'])) {
                    $params['relations'] = [];
                }
            }

            foreach ($params['relations'] as $relation) {
                if (empty($relation['title']) || empty($relation['mobile'])) {
                    return $response->withJson(Valid::addErrors([], 'relation', 'student_relation_error'), 200);
                }
                if (!Util::isMobile($relation['mobile'])) {
                    return $response->withJson(Valid::addErrors([], 'relation', 'student_tel_format_error'), 200);
                }
            }
        }
        if (!is_array($params['instruments'])) {
            return $response->withJson(Valid::addErrors([], 'relation', 'instruments_must_be_array'), 200);
        }
        foreach ($params['instruments'] as $instrument) {
            if (empty($instrument['has_instrument']) && isset($instrument['has_instrument']) && $instrument['has_instrument'] !== "0") {
                return $response->withJson(Valid::addErrors([], 'has_instrument', 'has_instrument_is_required'), 200);
            }
            if (empty($instrument['start_year'])) {
                return $response->withJson(Valid::addErrors([], 'start_year', 'start_year_is_required'), 200);
            }
            if (empty($instrument['apps']) || !is_array($instrument['apps'])) {
                return $response->withJson(Valid::addErrors([], 'start_year', 'app_is_required'), 200);
            }
            foreach ($instrument['apps'] as $app) {
                if (empty($app['id'])) {
                    return $response->withJson(Valid::addErrors([], 'app_id', 'app_id_is_required'), 200);
                }
                if (empty($app['ca_id'])) {
                    return $response->withJson(Valid::addErrors([], 'ca_id', 'ca_id_is_required'), 200);
                }
            }
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        StudentService::updateStudentDetail($params);
        $db->commit();

        return $response->withJson([
            'code' => 0,
            'data' => []
        ], 200);
    }


    /**
     * 添加学生
     * @param Request $request
     * @param Response $response
     * @param $argv
     * @return Response
     * @throws \Exception
     */
    public function add(Request $request, Response $response, $argv)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'student_name_is_required'
            ],
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'user_mobile_is_required'
            ],
            [
                'key' => 'channel_id',
                'type' => 'required',
                'error_code' => 'channel_id_is_required'
            ],
            [
                'key' => 'app_id',
                'type' => 'required',
                'error_code' => 'app_id_is_required',
            ],
            [
                'key' => 'channel_id',
                'type' => 'numeric',
                'error_code' => 'channel_id_must_be_numeric',
            ],
            [
                'key' => 'app_id',
                'type' => 'numeric',
                'error_code' => 'app_id_must_be_numeric',
            ],
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $params['app_id'] = UserCenter::AUTH_APP_ID_STUDENT;
        if (!Util::isMobile($params['mobile'])) {
            return $response->withJson(Valid::addErrors([], 'relation', 'student_tel_format_error'), StatusCode::HTTP_OK);
        }
        if (mb_strlen($params['name']) > 10) {
            return $response->withJson(Valid::addErrors([], 'student_name', 'student_name_length_more_then_10'), StatusCode::HTTP_OK);
        }
        if (isset($params['relations'])) {
            //如果没填关联人 则置空
            if (count($params['relations']) == 1) {
                $row = current($params['relations']);
                if (empty($row['title']) && empty($row['mobile'])) {
                    $params['relations'] = [];
                }
            }

            foreach ($params['relations'] as $relation) {
                if (empty($relation['title']) || empty($relation['mobile'])) {
                    return $response->withJson(Valid::addErrors([], 'relation', 'student_relation_error'), StatusCode::HTTP_OK);
                }
                if (!Util::isMobile($relation['mobile'])) {
                    return $response->withJson(Valid::addErrors([], 'relation', 'student_tel_format_error'), StatusCode::HTTP_OK);
                }
            }
        }
        //检查手机号是否已经注册
        $student = StudentService::getStudentByMobile($params['mobile']);
        if (!empty($student)) {
            return $response->withJson(Valid::addErrors([], 'student_mobile', 'mobile_is_exist'), StatusCode::HTTP_OK);
        }
        $channel = ChannelService::getChannelById($params['channel_id']);
        if (empty($channel)) {
            return $response->withJson(Valid::addErrors([], 'channel_id_error', 'channel_id_error'), StatusCode::HTTP_OK);
        }
        $params['channel_level'] = $channel['level'];
        //设置学生手动添加属性
        $params['is_manual'] = StudentAppModel::MANUAL_ENTRY;
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $res = StudentService::studentRegister($params, $this->ci['employee']['id']);
        if ($res['code'] == Valid::CODE_PARAMS_ERROR) {
            $db->rollBack();
            return $response->withJson($res, StatusCode::HTTP_OK);
        }

        $db->commit();

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => []
        ], StatusCode::HTTP_OK);
    }


    /**
     * 批量分配课管
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function batchAssignCC(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'students',
                'type' => 'required',
                'error_code' => 'student_id_is_required',
            ],
            [
                'key' => 'students',
                'type' => 'array',
                'error_code' => 'student_id_is_array',
            ],
            [
                'key' => 'cc_id',
                'type' => 'required',
                'error_code' => 'cc_id_is_required',
            ],
            [
                'key' => 'app_id',
                'type' => 'required',
                'error_code' => 'app_id_is_required',
            ],
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $employee = EmployeeModel::getById($params['cc_id']);
        if (empty($employee)) {
            return $response->withJson(Valid::addErrors([], 'cc_id', 'cc_is_not_exist'), StatusCode::HTTP_OK);
        }

        StudentAppService::batchUpdateStudentCA($params['app_id'],$params['students'],$params['ca_id']);


        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => []
        ], StatusCode::HTTP_OK);
    }

}