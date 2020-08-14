<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/04/04
 * Time: 5:14 PM
 */

namespace App\Controllers\OrgWeb;

use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\EmployeeModel;
use App\Models\StudentModel;
use App\Models\UserWeixinModel;
use App\Services\Queue\PushMessageTopic;
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

        //获取没有分配课管的学生信息,只有第一次分配课管的学生才会微信消息推送
        $userInfo = StudentModel::getNoCourseStudent($params['student_ids']);
        //根据课管ID获取课管信息
        $courseInfo = EmployeeModel::getRecord(['id' => $params['course_manage_id']], ['wx_nick', 'wx_thumb', 'wx_qr']);
        $courseInfoResult = empty($courseInfo['wx_nick']) && empty($courseInfo['wx_thumb']) && empty($courseInfo['wx_qr']);
        if (empty($userInfo) || $courseInfoResult) {
            $toBePushedStudentInfo = [];
        } else {
            //获取没有分配课管的学生openId
            $userOpenIdInfo = UserWeixinModel::getBoundUserIds(array_column($userInfo, 'id'), UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT);
            $toBePushedStudentInfo = array_combine(array_column($userInfo, 'id'), $userInfo);
            foreach ($userOpenIdInfo as $key => $value){
                $toBePushedStudentInfo[$value['user_id']]['open_id'] = $value['open_id'];
            }
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

        if (empty($toBePushedStudentInfo)) {
            return HttpHelper::buildResponse($response, []);
        }
        StudentService::allotCoursePushMessage($params['course_manage_id'], $courseInfo, $toBePushedStudentInfo);
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

    /**
     * 学生信息模糊搜索
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function fuzzySearchStudent(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'mobile',
                'type' => 'length',
                'value' => 11,
                'error_code' => 'invalid_mobile'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $studentList = StudentService::fuzzySearchStudent($params, ['id', 'mobile', 'name']);
        //返回数据
        return HttpHelper::buildResponse($response, $studentList);
    }

}