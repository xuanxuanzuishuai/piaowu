<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-26
 * Time: 19:23
 */

namespace App\Controllers\Schedule;


use App\Controllers\ControllerBase;
use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\ClassTaskModel;
use App\Models\ClassUserModel;
use App\Models\STClassModel;
use App\Services\ClassTaskService;
use App\Services\ClassUserService;
use App\Services\ScheduleService;
use App\Services\STClassService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class STClass extends ControllerBase
{

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
                'key' => 'cts',
                'type' => 'required',
                'error_code' => 'cts_is_required',
            ],
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'class_name_is_required',
            ],
            [
                'key' => 'class_lowest',
                'type' => 'required',
                'error_code' => 'class_lowest_is_required',
            ],
            [
                'key' => 'class_highest',
                'type' => 'required',
                'error_code' => 'class_highest_is_required',
            ],
            [
                'key' => 'campus_id',
                'type' => 'required',
                'error_code' => 'campus_id_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }

        $cts = ClassTaskService::checkCTs($params['cts']);
        if (!empty($cts['code']) && $cts['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($cts, StatusCode::HTTP_OK);
        }

        if (!empty($params['students'])) {
            $result = ClassUserService::checkStudent($params['students'], $cts, $params['class_highest']);
            if ($result !== true) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
        }
        if (!empty($params['teachers'])) {
            $result = ClassUserService::checkTeacher($params['teachers'], $cts, 2);
            if ($result !== true) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
        }
        $stc['lesson_num'] = $cts['lesson_num'];
        $stc['name'] = $params['name'];
        $stc['campus_id'] = $params['campus_id'];
        $stc['class_lowest'] = $params['class_lowest'];
        $stc['class_highest'] = $params['class_highest'];
        $stc['create_time'] = time();
        $stc['status'] = STClassModel::STATUS_NORMAL;
        $stc['student_num'] = count($params['students']);
        $stcId = STClassService::addSTClass($stc, $cts, $params['students'], $params['teachers']);
        if (empty($stcId)) {
            return $response->withJson(Valid::addErrors([], 'class_failure', 'class_add_failure'), StatusCode::HTTP_OK);
        }
        $stc = STClassService::getSTClassDetail($stcId);
        return $response->withJson([
            'code' => 0,
            'data' => ['stc' => $stc]
        ], 200);
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
                'key' => 'class_id',
                'type' => 'required',
                'error_code' => 'class_id_is_required',
            ],
            [
                'key' => 'cts',
                'type' => 'required',
                'error_code' => 'sts_is_required',
            ],
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'class_name_is_required',
            ],
            [
                'key' => 'class_lowest',
                'type' => 'required',
                'error_code' => 'class_lowest_is_required',
            ],
            [
                'key' => 'class_highest',
                'type' => 'required',
                'error_code' => 'class_highest_is_required',
            ],
            [
                'key' => 'campus_id',
                'type' => 'required',
                'error_code' => 'campus_id_is_required',
            ],
            [
                'key' => 'status',
                'type' => 'required',
                'error_code' => 'status_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }

        $class = STClassService::getSTClassDetail($params['class_id']);
        if (empty($class)) {
            return $response->withJson(Valid::addErrors([], 'class', 'class_not_exist'), StatusCode::HTTP_OK);
        }
        if ($class['status'] != STClassModel::STATUS_NORMAL) {
            return $response->withJson(Valid::addErrors([], 'class', 'class_status_is_invalid'), StatusCode::HTTP_OK);
        }

        $newStc['id'] = $class['id'];
        $newStc['name'] = $params['name'];
        $newStc['status'] = $params['status'];
        $newStc['campus_id'] = $params['campus_id'];
        $newStc['class_lowest'] = $params['class_lowest'];
        $newStc['class_highest'] = $params['class_highest'];
        $newStc['update_time'] = time();
        $newStc['finish_num'] = 0;


        $cts = ClassTaskService::checkCTs($params['cts'], $newStc['id']);

        if (!empty($cts['code']) && $cts['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($cts, StatusCode::HTTP_OK);
        }
        $newStc['lesson_num'] = $cts['lesson_num'];

        $studentIds = $ssuIds = $stuIds = [];
        if (!empty($class['students'])) {
            foreach ($class['students'] as $student) {
                $studentIds[] = $student['user_id'];
                $ssuIds[] = $student['id'];
            }
        }
        if (!empty($params['students'])) {
            $result = ClassUserService::checkStudent($params['students'], $cts, $params['class_highest']);
            if ($result !== true) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
        }
        $newStc['student_num'] = count($params['students']);
        $teacherIds = [];
        if (!empty($class['teachers'])) {
            foreach ($class['teachers'] as $teacher) {
                $teacherIds[] = $teacher['user_id'];
                $stuIds[] = $teacher['id'];
            }
        }
        if (!empty($params['teachers'])) {
            $result = ClassUserService::checkTeacher($params['teachers'], $cts, 2);
            if ($result !== true) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
        }
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        STCLassService::modifyClass($newStc);
        if ($newStc['status'] == ClassTaskModel::STATUS_CANCEL) {
            ClassUserService::updateCUStatus(['class_id' => $class['id']], ClassUserModel::STATUS_CANCEL);
            ClassTaskService::updateCTStatus(['class_id' => $class['id']], ClassTaskModel::STATUS_CANCEL);
        } else {

            if (!empty($ssuIds)) {
                ClassUserService::unBindUser($ssuIds, $class['id']);
            }
            if (!empty($params['students'])) {
                ClassUserService::bindCUs($class['id'], [ClassUserModel::USER_ROLE_S => $params['students']]);
            }


            if (!empty($stuIds)) {
                ClassUserService::unBindUser($stuIds, $class['id']);
            }
            if (!empty($params['teachers'])) {
                ClassUserService::bindCUs($class['id'], [ClassUserModel::USER_ROLE_T => $params['teachers']]);
            }

            ClassTaskService::updateCTStatus(['class_id' => $class['id']], ClassTaskModel::STATUS_CANCEL);
            ClassTaskService::addCTs($class['id'], $cts);
        }
        $db->commit();
        $stc = STClassService::getSTClassDetail($newStc['id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['stc' => $stc]
        ]);

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
        if (isset($params['page'])) {
            list($params['page'], $params['count']) = Util::formatPageCount($params);
        } else {
            $params['page'] = -1;
        }
        list($num, $stcs) = STClassService::getSTClassList($params, $params['page'], $params['count']);
        return $response->withJson([
            'code' => 0,
            'data' => ['count' => $num, 'stcs' => $stcs]
        ], StatusCode::HTTP_OK);

    }

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
                'key' => 'class_id',
                'type' => 'required',
                'error_code' => 'class_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $stc = STClassService::getSTClassDetail($params['class_id']);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'stc' => $stc
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 开课
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function beginST(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'class_id',
                'type' => 'required',
                'error_code' => 'class_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $class = STClassService::getSTClassDetail($params['class_id']);
        if (empty($class)) {
            return $response->withJson(Valid::addErrors([], 'class', 'class_is_not_exist'), StatusCode::HTTP_OK);
        }
        if (empty($class['class_tasks'])) {
            return $response->withJson(Valid::addErrors([], 'class', 'class_task_is not_exist'), StatusCode::HTTP_OK);
        }
        if ($class['status'] != STClassModel::STATUS_NORMAL) {
            return $response->withJson(Valid::addErrors([], 'class', 'class_status_invalid'), StatusCode::HTTP_OK);
        }
        if (empty($class['students'])) {
            return $response->withJson(Valid::addErrors([], 'class', 'class_students_is_empty'), StatusCode::HTTP_OK);
        }
        if ($class['class_lowest'] > count($class['students'])) {
            return $response->withJson(Valid::addErrors([], 'class', 'class_students_is_less_than_min'), StatusCode::HTTP_OK);
        }
        if ($class['class_highest'] < count($class['students'])) {
            return $response->withJson(Valid::addErrors([], 'class', 'class_students_is_more_than_max'), StatusCode::HTTP_OK);
        }
        if (empty($class['teachers'])) {
            return $response->withJson(Valid::addErrors([], 'class', 'class_teachers_is_empty'), StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $result = ScheduleService::beginSchedule($class);
        if ($result === false) {
            $db->rollBack();
            return $response->withJson(Valid::addErrors([], 'class_schedule', 'class_create_schedule_failure'), StatusCode::HTTP_OK);
        } else {
            STClassService::modifyClass(['id' => $class['id'], 'status' => STClassModel::STATUS_BEGIN, 'update_time' => time()]);
        }
        $db->commit();

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
            ]
        ], StatusCode::HTTP_OK);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function cancelST(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'class_id',
                'type' => 'required',
                'error_code' => 'class_id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $class = STClassService::getSTClassDetail($params['class_id']);
        if (empty($class)) {
            return $response->withJson(Valid::addErrors([], 'class', 'class_is_not_exist'), StatusCode::HTTP_OK);
        }
        if ($class['status'] != STClassModel::STATUS_BEGIN) {
            return $response->withJson(Valid::addErrors([], 'class', 'class_status_is_invalid'), StatusCode::HTTP_OK);
        }
        if (!empty($class['students'])) {
            return $response->withJson(Valid::addErrors([], 'class', 'class_students_is_not_empty'), StatusCode::HTTP_OK);
        }
        if (!empty($class['teachers'])) {
            return $response->withJson(Valid::addErrors([], 'class', 'class_teachers_is_not_empty'), StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $res = ScheduleService::cancelScheduleByClassId($class['id']);
        if ($res == false) {
            $db->rollBack();
        }
        $res = ClassTaskService::updateCTStatus(['class_id' => $class['id']], ClassTaskModel::STATUS_CANCEL);
        if ($res == false) {
            $db->rollBack();
        }
        $res = STClassService::modifyClass(['id' => $class['id'], 'status' => ClassTaskModel::STATUS_CANCEL, 'update_time' => time()]);
        if ($res == false) {
            $db->rollBack();
        }
        $db->commit();
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
            ]
        ], StatusCode::HTTP_OK);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public static function copySTClass(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'class_ids',
                'type' => 'required',
                'error_code' => 'class_ids_is_required'
            ],
            [
                'key' => 'num',
                'type' => 'required',
                'error_code' => 'class_copy_time_is_required'
            ],
            [
                'key' => 'start_date',
                'type' => 'required',
                'error_code' => 'class_start_date_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $classes = [];
        foreach ($params['class_ids'] as $classId) {
            $class = STClassService::getSTClassDetail($classId);
            if (empty($class)) {
                return $response->withJson(Valid::addErrors([], 'class', 'class_is_not_exist'), StatusCode::HTTP_OK);
            }
            if (empty($class['class_tasks'])) {
                return $response->withJson(Valid::addErrors([], 'class', 'class_task_is not_exist'), StatusCode::HTTP_OK);
            }
            if (!in_array($class['status'], [STClassModel::STATUS_BEGIN, STClassModel::STATUS_NORMAL])) {
                return $response->withJson(Valid::addErrors([], 'class', 'class_status_invalid'), StatusCode::HTTP_OK);
            }
            $classes[] = $class;
        }
        $now = time();
        if (!empty($classes)) {
            $db = MysqlDB::getDB();
            $db->beginTransaction();
            foreach ($classes as $class) {
                $newCts = $newStc = [];

                $newStc['name'] = $class['name'];
                $newStc['status'] = STClassModel::STATUS_NORMAL;
                $newStc['campus_id'] = $class['campus_id'];
                $newStc['class_lowest'] = $class['class_lowest'];
                $newStc['class_highest'] = $class['class_highest'];
                $newStc['finish_num'] = 0;
                $newStc['lesson_num'] = $class['lesson_num'];
                $newStc['create_time'] = $now;
                foreach ($class['class_tasks'] as $key => $ct) {
                    $newCt = $ct;
                    $newCt['expire_start_date'] = $params['start_date'];
                    unset($newCt['expire_end_date']);
                    $newCts[$key] = $newCt;
                }

                for ($i = 0; $i < $params['num']; $i++) {
                    $cts = ClassTaskService::checkCTs($newCts);
                    if (!empty($cts['code']) && $cts['code'] == Valid::CODE_PARAMS_ERROR) {
                        $db->rollBack();
                        return $response->withJson($cts, StatusCode::HTTP_OK);
                    }
                    $stcId = STClassService::addSTClass($newStc, $cts);
                    if (empty($stcId)) {
                        $db->rollBack();
                        return $response->withJson(Valid::addErrors(['data' => ['result' => $class], 'code' => 1], 'class', 'class_copy_failure'), StatusCode::HTTP_OK);
                    }
                    unset($cts['lesson_num']);
                    $newCts = $cts;
                    foreach ($newCts as $key => $newCt) {
                        $newCt['expire_start_date'] = $newCt['expire_end_date'];
                        unset($newCt['expire_end_date']);
                        $newCts[$key] = $newCt;
                    }
                }

            }
            $db->commit();
        }
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
            ]
        ], StatusCode::HTTP_OK);

    }
}