<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 17:57
 */

namespace App\Controllers\Schedule;


use App\Controllers\ControllerBase;
use App\Libs\MysqlDB;
use App\Libs\Valid;
use App\Models\ClassUserModel;
use App\Models\STClassModel;
use App\Services\ClassUserService;
use App\Services\ScheduleService;
use App\Services\ScheduleUserService;
use App\Services\STClassService;
use App\Services\StudentAccountService;
use App\Services\StudentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class ClassUser extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function bindStudents(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'class_id',
                'type' => 'required',
                'error_code' => 'class_is_required',
            ],
            [
                'key' => 'students',
                'type' => 'required',
                'error_code' => 'students_is_required',
            ],

        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $class = STClassService::getSTClassDetail($params['class_id']);
        if (empty($class)) {
            return $response->withJson(Valid::addErrors([], 'class', 'class_not_exist'), StatusCode::HTTP_OK);
        }
        if ($class['status'] != STClassModel::STATUS_BEGIN) {
            return $response->withJson(Valid::addErrors([], 'class', 'class_status_invalid'), StatusCode::HTTP_OK);
        }

        $params['students'] = StudentService::arrayPlus($params['students']);
        foreach($class['students'] as $student) {
            if(key_exists($student['user_id'], $params['students'])){
                unset($params['students'][$student['user_id']]);
            }
        }
        if (empty($params['students'])) {
            return $response->withJson(['code' => Valid::CODE_SUCCESS, 'data' => ['st' => $class]], StatusCode::HTTP_OK);
        }

        $result = ClassUserService::checkStudent($params['students'], $class['class_tasks'], $class['class_highest'] - count($class['students']));
        if ($result !== true) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $balances = StudentAccountService::checkBalance($params['students']);
        if ($balances !== true) {
            return $response->withJson($balances, StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $ctIds = array_column($class['class_tasks'], 'id');
        $res = ClassUserService::bindCUs($class['id'], [ClassUserModel::USER_ROLE_S => $params['students']], $ctIds);
        if ($res == false) {
            $db->rollBack();
            return $response->withJson(Valid::addErrors([], 'class_bind_student', 'class_bind_student_error'), StatusCode::HTTP_OK);
        }

        $res = ScheduleService::bindSUs($class['id'], $params['students'], ClassUserModel::USER_ROLE_S, $ctIds);
        if ($res == false) {
            $db->rollBack();
            return $response->withJson(Valid::addErrors([], 'class_bind_student', 'class_bind_student_error'), StatusCode::HTTP_OK);
        }

        STClassService::modifyClass(['id' => $class['id'], 'student_num[+]' => count($params['students'])]);
        $db->commit();
        $st = STClassService::getSTClassDetail($class['id']);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['st' => $st],
        ], StatusCode::HTTP_OK);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function bindTeachers(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'class_id',
                'type' => 'required',
                'error_code' => 'class_is_required',
            ],
            [
                'key' => 'teachers',
                'type' => 'required',
                'error_code' => 'teachers_is_required',
            ],

        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $class = STClassService::getSTClassDetail($params['class_id']);
        if (empty($class)) {
            return $response->withJson(Valid::addErrors([], 'class', 'class_not_exist'), StatusCode::HTTP_OK);
        }
        if ($class['status'] != STClassModel::STATUS_BEGIN) {
            return $response->withJson(Valid::addErrors([], 'class', 'class_status_invalid'), StatusCode::HTTP_OK);
        }

        foreach($class['teachers'] as $teacher) {
            if(key_exists($teacher['user_id'], $params['teachers'])){
                unset($params['teachers'][$teacher['user_id']]);
            }
        }
        if (empty($params['teachers'])) {
            return $response->withJson(['code' => 0, 'data' => ['stc' => $class]], StatusCode::HTTP_OK);
        }

        $result = ClassUserService::checkTeacher($params['teachers'], $class['class_tasks'], $class['teachers']);
        if ($result !== true) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $res = ScheduleService::bindSUs($class['id'], $params['teachers'], ClassUserModel::USER_ROLE_T);
        if ($res == false) {
            $db->rollBack();
            return $response->withJson(Valid::addErrors([], 'class_bind_student', 'class_bind_teacher_error'), StatusCode::HTTP_OK);
        }
        $res = ClassUserService::bindCUs($class['id'], [ClassUserModel::USER_ROLE_T => $params['teachers']]);
        if ($res == false) {
            $db->rollBack();
            return $response->withJson(Valid::addErrors([], 'class_bind_student', 'class_bind_teacher_error'), StatusCode::HTTP_OK);
        }
        $db->commit();
        $stc = STClassService::getSTClassDetail($class['id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['stc' => $stc]
        ], StatusCode::HTTP_OK);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function unbindUsers(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'class_id',
                'type' => 'required',
                'error_code' => 'st_is_required',
            ],
            [
                'key' => 'stu_ids',
                'type' => 'required',
                'error_code' => 'user_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $class = STClassService::getSTClassDetail($params['class_id']);
        if (empty($class)) {
            return $response->withJson(Valid::addErrors([], 'class', 'class_not_exist'), StatusCode::HTTP_OK);
        }
        if ($class['status'] != STClassModel::STATUS_BEGIN) {
            return $response->withJson(Valid::addErrors([], 'class', 'class_status_invalid'), StatusCode::HTTP_OK);
        }
        $users = $cuIds = [];
        if (!empty($class['students'])) {
            foreach ($class['students'] as $user) {
                if (in_array($user['id'], $params['stu_ids'])) {
                    $users[ClassUserModel::USER_ROLE_S][] = $user['user_id'];
                    $cuIds[] = $user['id'];
                }
            }
        }
        if (!empty($class['teachers'])) {
            foreach ($class['teachers'] as $user) {
                if (in_array($user['id'], $params['stu_ids'])) {
                    $users[ClassUserModel::USER_ROLE_T][] = $user['user_id'];
                    $cuIds[] = $user['id'];
                }
            }
        }
        $beginDate = empty($params['beginDate']) ? date("Y-m-d") : $params['beginDate'];
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        if (!empty($users)) {
            ScheduleUserService::cancelScheduleUsers($users, $class['id'], $beginDate);
        }
        if (!empty($cuIds)) {
            ClassUserService::unBindUser($cuIds, $class['id']);
        }
        if (!empty($users[ClassUserModel::USER_ROLE_S])) {
            ClassUserService::updateStudentPrice($class['id'], $users[ClassUserModel::USER_ROLE_S]);
        }
        STClassService::modifyClass(['id' => $class['id'], 'student_num[-]' => count($users[ClassUserModel::USER_ROLE_S])]);
        $db->commit();

        $stc = STClassService::getSTClassDetail($class['id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['stc' => $stc]
        ], StatusCode::HTTP_OK);
    }
}