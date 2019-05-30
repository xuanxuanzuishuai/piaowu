<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/4/23
 * Time: 15:41
 */

namespace App\Controllers\StudentOrgWX;

use App\Controllers\ControllerBaseForOrg;
use App\Libs\Valid;
use App\Models\ClassTaskPriceModel;
use App\Models\ClassUserModel;
use App\Models\CourseModel;
use App\Services\ClassTaskService;
use App\Services\ClassUserService;
use App\Services\STClassService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class StudentClass extends ControllerBaseForOrg
{
    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 预约体验课班级
     */
    public function OrderClass(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'class_id',
                'type' => 'required',
                'error_code' => 'class_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $userInfo = $this->ci['user_info'];
        //是否已有体验课
        $scheduleInfo = ClassUserService::getUserClassInfo(['user_id' => $userInfo['user_id']], ClassUserModel::STATUS_NORMAL);
        if (empty($scheduleInfo)) {
            $newStc['id'] = $params['class_id'];
            $newStc['update_time'] = time();
            $newStc['student_num[+]'] = 1;
            STCLassService::modifyClass($newStc);
            //当前班级体验课排课安排
            $classTaskInfo = ClassTaskService::getClassTaskInfo($params['class_id']);
            $oprice = CourseModel::getCourseOprice($classTaskInfo['course_id']);
            //课程计划添加学生
            ClassUserModel::addCU([
                'status' => ClassUserModel::STATUS_NORMAL,
                'class_id' => $params['class_id'],
                'user_id' => $userInfo['user_id'],
                'user_role' => ClassUserModel::USER_ROLE_S,
                'create_time' => time()]);
            //课单价添加
            ClassTaskPriceModel::insertRecord([
                'class_id' => $params['class_id'],
                'c_t_id' => $classTaskInfo['id'],
                'student_id' => $userInfo['user_id'],
                'price' => $oprice,
                'status' => ClassUserModel::STATUS_NORMAL,
                'create_time' => time()], false);
        }
        return $response->withJson([
            'code' => 0,
        ]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 当前学生的约课记录
     */
    public function getOrderClass(Request $request, Response $response)
    {
        $userInfo = $this->ci['user_info'];
        $scheduleInfo = ClassUserService::getUserClassInfo(['user_id' => $userInfo['user_id']], ClassUserModel::STATUS_NORMAL);
        return $response->withJson([
            'code' => 0,
            'data' => [
                'schedule_info' => $scheduleInfo
            ]
        ]);
    }

}
