<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/04/04
 * Time: 5:14 PM
 */

namespace App\Controllers\OrgWeb;

use App\Libs\Valid;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Services\StudentService;
use App\Libs\HttpHelper;
use App\Libs\Exceptions\RunTimeException;
use App\Controllers\ControllerBase;
use App\Libs\MysqlDB;

class Student extends ControllerBase
{

    /**
     * dss用户导入真人流转
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function syncDataToCrm(Request $request, Response $response)
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
            ],
            [
                'key' => 'sync_status',
                'type' => 'required',
                'error_code' => 'sync_status_is_required',
            ],
            [
                'key' => 'sync_status',
                'type' => 'integer',
                'error_code' => 'sync_status_must_be_integer'
            ],
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            StudentService::syncStudentToCrm($params['student_id'], $params['sync_status']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }
        //返回数据
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 学生分配课管
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function allotCourseManage(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'student_ids',
                'type' => 'required',
                'error_code' => 'student_ids_is_required',
            ],
            [
                'key' => 'student_ids',
                'type' => 'array',
                'error_code' => 'student_id_must_be_array'
            ],
            [
                'key' => 'course_manage_id',
                'type' => 'required',
                'error_code' => 'course_manage_id_is_required',
            ],
            [
                'key' => 'course_manage_id',
                'type' => 'integer',
                'error_code' => 'course_manage_id_must_be_integer'
            ],
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            //开启事务
            $db = MysqlDB::getDB();
            $db->beginTransaction();
            StudentService::allotCourseManage($params['student_ids'], $params['course_manage_id'], $this->getEmployeeId());
        } catch (RunTimeException $e) {
            $db->rollBack();
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }
        //提交事务 返回数据
        $db->commit();
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 查看学生明文手机号码
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getStudentMobile(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'student_ids',
                'type' => 'required',
                'error_code' => 'student_ids_is_required',
            ],
            [
                'key' => 'student_ids',
                'type' => 'array',
                'error_code' => 'student_id_must_be_array'
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $studentInfo = StudentService::getStudentSelfSecret($params['student_ids'], $this->getEmployeeId(), ['id', 'mobile']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }
        //返回数据
        return HttpHelper::buildResponse($response, $studentInfo);
    }
}