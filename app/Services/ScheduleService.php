<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-22
 * Time: 16:11
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\ClassTaskModel;
use App\Models\ScheduleModel;
use App\Models\ClassUserModel;
use App\Models\ScheduleUserModel;
use App\Models\STClassModel;

class ScheduleService
{

    /**
     * @param $class
     * @return bool
     */
    public static function beginSchedule($class)
    {
        $now = time();
        foreach ($class['class_tasks'] as $ct) {
            $beginDate = $ct['expire_start_date'];
            $weekday = date("w", strtotime($beginDate));
            if ($weekday <= $ct['weekday']) {
                $beginTime = strtotime($beginDate . " " . $ct['start_time']) + 86400 * ($ct['weekday'] - $weekday);
            } else {
                $beginTime = strtotime($beginDate . " " . $ct['start_time']) + 86400 * (7 - ($weekday - $ct['weekday']));
            }
            for ($i = 0; $i < $ct['period']; $i++) {
                $schedule = [
                    'classroom_id' => $ct['classroom_id'],
                    'course_id' => $ct['course_id'],
                    'duration' => $ct['duration'],
                    'start_time' => $beginTime,
                    'end_time' => $beginTime + $ct['duration'],
                    'create_time' => $now,
                    'status' => ScheduleModel::STATUS_BOOK,
                    'org_id' => $class['org_id'],
                    'class_id' => $class['id'],
                ];
                $sId = ScheduleModel::insertSchedule($schedule);
                if (empty($sId)) {
                    return false;
                }
                $users = [];
                foreach ($class['students'] as $student) {
                    if ($student['status'] == ScheduleUserModel::STATUS_NORMAL) {
                        $users[] = ['price' => $student['price'], 'schedule_id' => $sId, 'user_id' => $student['user_id'], 'user_role' => $student['user_role'], 'status' => ScheduleModel::STATUS_BOOK, 'create_time' => $now, 'user_status' => ScheduleUserModel::STUDENT_STATUS_BOOK];
                    }
                }
                foreach ($class['teachers'] as $teacher) {
                    if ($teacher['status'] == ScheduleUserModel::STATUS_NORMAL) {
                        $users[] = ['price' => 0, 'schedule_id' => $sId, 'user_id' => $teacher['user_id'], 'user_role' => $teacher['user_role'], 'status' => ScheduleModel::STATUS_BOOK, 'create_time' => $now, 'user_status' => ScheduleUserModel::TEACHER_STATUS_SET];
                    }
                }
                $flag = ScheduleUserModel::insertSUs($users);
                if ($flag == false)
                    return false;
                $beginTime += 7 * 86400;
            }
        }


        return true;
    }

    /**
     * @param $params
     * @param int $page
     * @param int $count
     * @return array
     */
    public static function getList($params,$page = -1,$count = 20) {
        $sIds = $result = [];
        list($count, $schedules) = ScheduleModel::getList($params, $page, $count);
        foreach ($schedules as $schedule) {
            $schedule = self::formatSchedule($schedule);
            $sIds[] = $schedule['id'];
            $result[$schedule['id']] = $schedule;
        }
        if(!empty($sIds)) {
            $sus = ScheduleUserModel::getSUBySIds($sIds);
            foreach ($sus as $su) {
                $su = self::formatScheduleUser($su);
                if ($su['user_role'] == ClassUserModel::USER_ROLE_S) {
                    $result[$su['schedule_id']]['students']++;
                } else
                    $result[$su['schedule_id']]['teachers']++;
            }
        }
        return [$count, $result];
    }

    /**
     * @param $sId
     * @return null
     */
    public static function getDetail($sId) {
        $schedule = ScheduleModel::getDetail($sId);
        if (empty($schedule)) {
            return null;
        }
        $schedule = self::formatSchedule($schedule);
        $sus = ScheduleUserModel::getSUBySIds([$schedule['id']]);
        foreach ($sus as $su) {
            $su = self::formatScheduleUser($su);
            if ($su['user_role'] == ClassUserModel::USER_ROLE_S) {
                $schedule['students'][] = $su;
            } else
                $schedule['teachers'][] = $su;
        }
        return $schedule;
    }

    /**
     * @param $schedule
     * @return mixed
     */
    public static function formatSchedule($schedule) {
        $schedule['s_status'] = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_STATUS,$schedule['status']);
        $schedule['course_type'] = DictService::getKeyValue(Constants::DICT_COURSE_TYPE,$schedule['course_type']);
        return $schedule;
    }

    /**
     * @param $su
     * @return mixed
     */
    public static function formatScheduleUser($su) {
        $su['su_user_role'] = DictService::getKeyValue(Constants::DICT_TYPE_CLASS_USER_ROLE,$su['user_role']);
        $su['su_status'] = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_USER_STATUS,$su['status']);
        if($su['user_role'] == ClassUserModel::USER_ROLE_S) {
            $su['student_status'] = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_STUDENT_STATUS, $su['user_status']);
        }
        if($su['user_role'] == ClassUserModel::USER_ROLE_T) {
            $su['teacher_status'] = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_TEACHER_STATUS, $su['user_status']);
        }
        $su['price'] = floatval($su['price']/100);
        return $su;
    }

    /**
     * @param $schedule
     * @return array|bool
     */
    public static function checkSchedule($schedule){
        $schedules = ScheduleModel::checkSchedule($schedule);
        return empty($schedules) ? true : $schedules;
    }

    /**
     * 修改课程
     * @param $newSchedule
     * @param $classTasks
     * @param $params
     * @param $time
     * @return array | int
     */
    public static function modifySchedule($newSchedule, $classTasks, $params, $time)
    {
        // 修改schedule
        $result = ScheduleModel::modifySchedule($newSchedule);
        if (!$result) {
            return Valid::addErrors([], 'schedule_failure', 'schedule_change_failure');
        }

        $classroom = ClassroomService::getById($params['classroom_id']);
        $originClass = STClassService::getById($newSchedule['class_id']);
        $class['lesson_num'] = 1;
        $class['name'] = $originClass['name'];
        $class['campus_id'] = $classroom['campus_id'];
        $class['org_id'] = $classroom['org_id'];
        $class['class_lowest'] = $originClass['class_lowest'];
        $class['class_highest'] = $originClass['class_highest'];
        $class['create_time'] = $time;
        $class['status'] = STClassModel::STATUS_CHANGE;
        $class['student_num'] = count($params['students']);
        $class['real_schedule_id'] = $newSchedule['id'];

        // 创建class, class_task, class_user
        $classId = STClassService::addSTClass($class, $classTasks, $params['students'], $params['teachers']);
        if (empty($classId)) {
            return Valid::addErrors([], 'class_failure', 'class_add_failure');
        }

        // 修改schedule_user
        ScheduleUserService::unBindUser($newSchedule['id'], $time);
        $flag = ScheduleUserService::addScheduleUser($params['students'], $params['teachers'], $newSchedule['id'], $time);
        if (is_null($flag)) {
            return Valid::addErrors([], 'schedule_user_failure', 'schedule_add_user_failure');
        }
        return 0;
    }

    /**
     * @param $stId
     * @return bool
     */
    public static function cancelScheduleByClassId($stId) {
        return ScheduleModel::modifyScheduleByClassId(['status'=>ScheduleModel::STATUS_CANCEL,'update_time'=>time()],['class_id'=>$stId,'status'=>ScheduleModel::STATUS_BOOK]);
    }

    /**
     * @param $stId
     * @param $users
     * @param $userRole
     * @return bool
     */
    public static function bindSUs($stId,$users,$userRole)
    {
        $sus = [];
        $now = time();
        list($count, $schedules) = self::getList(['class_id' => $stId, 'status' => ScheduleModel::STATUS_BOOK]);
        foreach ($schedules as $schedule) {
            foreach ($users as $userId => $value) {
                if ($userRole == ClassUserModel::USER_ROLE_S) {
                    $price = $value * 100;
                } else {
                    $price = 0;
                    $userRole = $value;
                }
                $suStatus = $schedule['status'] != ScheduleModel::STATUS_BOOK ? ScheduleUserModel::STATUS_CANCEL : ScheduleUserModel::STATUS_NORMAL;
                $userStatus = $userRole == ClassUserModel::USER_ROLE_S ? ScheduleUserModel::STUDENT_STATUS_BOOK : ScheduleUserModel::TEACHER_STATUS_SET;
                $sus[] = ['price' => $price, 'schedule_id' => $schedule['id'], 'user_id' => $userId, 'user_role' => $userRole, 'user_status' => $userStatus, 'status' => $suStatus, 'create_time' => $now];
            }
        }
        if (!empty($sus)) {
            $ret = ScheduleUserModel::insertSUs($sus);
            if (is_null($ret))
                return false;
        }
        return true;
    }

    /**
     * 下课
     * @param $schedule
     */
    public static function finish($schedule)
    {
        $now = time();
        // change schedule status
        ScheduleModel::updateRecord($schedule['id'], ['status' => ScheduleModel::STATUS_FINISH, 'update_time' => $now]);

        // class finish_num
        STClassService::modifyClass(['id' => $schedule['class_id'], 'finish_num[+]' => 1, 'update_time' => $now]);
    }

    /** 学员上课记录
    * @param $orgId
    * @param $page
    * @param $count
    * @param $params
    * @return array
    */
    public static function attendRecord($orgId, $page, $count, $params)
    {
        list($records, $total) = ScheduleModel::attendRecord($orgId, $page, $count, $params);

        foreach ($records as &$r) {
            $r['status']   = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_STATUS, $r['status']);
            $r['duration'] /= 60;
        }

        return [$records, $total];
    }

    /**
     * 新增课程
     * @param $classTasks
     * @param $schedule
     * @param $time
     * @param $params
     * @return int|mixed|null|string
     */
    public static function addSchedule($classTasks, $schedule, $time, $params)
    {
        $classroom = ClassroomService::getById($params['classroom_id']);
        $class['lesson_num'] = 1;
        $class['name'] = '临时班课';
        $class['campus_id'] = $classroom['campus_id'];
        $class['org_id'] = $classroom['org_id'];
        $class['class_lowest'] = 1;
        $class['class_highest'] = $params['class_highest'];
        $class['create_time'] = $time;
        $class['status'] = STClassModel::STATUS_NORMAL;
        $class['student_num'] = count($params['students']);

        // 创建class, class_task, class_user
        $classId = STClassService::addSTClass($class, $classTasks, $params['students'], $params['teachers']);
        if (empty($classId)) {
            return Valid::addErrors([], 'class_failure', 'class_add_failure');
        }

        $schedule['class_id'] = $classId;
        $sId = ScheduleModel::insertSchedule($schedule);
        if (empty($sId)) {
            return Valid::addErrors([], 'schedule', 'add_schedule_failure');
        }

        $flag = ScheduleUserService::addScheduleUser($params['students'], $params['teachers'], $sId, $time);
        if ($flag == false) {
            return Valid::addErrors([], 'schedule', 'add_schedule_failure');
        }
        return $sId;
    }

    /**
     * check before添加课程、调整课程
     * 1. check schedule
     * 2. check schedule_user
     * 3. check class_task
     * 4. check class_user
     * @param $params
     * @param $schedule
     * @param $time
     * @param null $classId
     * @return array|bool
     */
    public static function checkScheduleAndClassTask($params, $schedule, $time, $classId = null)
    {
        if (empty($params['students']) || !is_array($params['students'])) {
            return Valid::addErrors([], 'students', 'students_is_required');
        }
        if (empty($params['teachers']) || !is_array($params['teachers'])) {
            return Valid::addErrors([], 'teachers', 'teacher_is_required');
        }

        $classroom = ClassroomService::getById($params['classroom_id']);
        $course = CourseService::getCourseById($params['course_id']);

        // check schedule, check schedule_user
        $checkSchedule = self::checkSchedule($schedule);
        if ($checkSchedule != true) {
            return Valid::addErrors([], 'class_task_classroom', 'class_task_classroom_error');
        }

        $originScheduleId = $params['schedule_id'] ?? 0;
        $checkStudent = ScheduleUserService::checkScheduleUser(array_keys($params['students']),
            ScheduleUserModel::USER_ROLE_STUDENT, $schedule['start_time'],
            $schedule['end_time'], $originScheduleId);
        if ($checkStudent == true) {
            return Valid::addErrors([], 'class_task_classroom', 'class_student_time_error');
        }

        $checkTeacher = ScheduleUserService::checkScheduleUser(array_keys($params['teachers']),
            [ScheduleUserModel::USER_ROLE_TEACHER, ScheduleUserModel::USER_ROLE_CLASS_TEACHER],
            $schedule['start_time'], $schedule['end_time'], $originScheduleId);
        if ($checkTeacher == true) {
            return Valid::addErrors([], 'class_task_classroom', 'class_teacher_time_error');
        }

        // check class_task, class_user
        $classTask = [
            'classroom_id' => $params['classroom_id'],
            'start_time' => date("H:i", $params['start_time']),
            'end_time' => date("H:i", $params['start_time'] + $course['duration']),
            'course_id' => $params['course_id'],
            'weekday' => date('w', $params['start_time']),
            'status' => ClassTaskModel::STATUS_NORMAL,
            'create_time' => $time,
            'org_id' => $classroom['org_id'],
            'expire_start_date' => date('Y-m-d', $params['start_time']),
            'expire_end_date' => date('Y-m-d', $params['start_time'] + Util::TIMESTAMP_ONEDAY),
            'period' => 1,
        ];
        if (!empty($classId)) {
            $classTask['class_id'] = $classId;
        }

        $checkRes = ClassTaskService::checkCT($classTask);
        if ($checkRes !== true) {
            return Valid::addErrors(['data' => ['result' => $classTask]], 'class_task_classroom', 'class_task_classroom_error');
        }

        $classTasks[] = $classTask;
        $checkStudent = ClassUserService::checkStudent($params['students'], $classTasks, $params['class_highest']);
        if ($checkStudent !== true) {
            return $checkStudent;
        }
        $checkTeacher = ClassUserService::checkTeacher($params['teachers'], $classTasks);
        if ($checkTeacher !== true) {
            return $checkTeacher;
        }

        return $classTasks;
    }

    /**
     * 取消课程
     * @param $schedule
     */
    public static function cancelSchedule($schedule)
    {
        ScheduleModel::updateRecord($schedule['id'], ['status' => ScheduleModel::STATUS_CANCEL, 'update_time' => time()]);
    }
}