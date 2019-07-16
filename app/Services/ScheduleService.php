<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-22
 * Time: 16:11
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\OpernCenter;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\ClassTaskModel;
use App\Models\ScheduleExtendModel;
use App\Models\ScheduleModel;
use App\Models\ClassUserModel;
use App\Models\ScheduleUserModel;
use App\Models\STClassModel;

class ScheduleService
{

    /**
     * 班级开课
     * @param $class
     * @return array|bool
     */
    public static function beginSchedule($class)
    {
        $now = time();
        foreach ($class['class_tasks'] as $key => $ct) {
            $beginDate = $ct['expire_start_date'];
            $weekday = date("w", strtotime($beginDate));
            if ($weekday <= $ct['weekday']) {
                $beginTime = strtotime($beginDate . " " . $ct['start_time']) + 86400 * ($ct['weekday'] - $weekday);
            } else {
                $beginTime = strtotime($beginDate . " " . $ct['start_time']) + 86400 * (7 - ($weekday - $ct['weekday']));
            }
            if ($beginTime < $now) {
                return Valid::addErrors([], 'start_time', 'schedule_start_time_is_error');
            }

            for ($i = 0; $i < $ct['period']; $i ++) {
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
                    'c_t_id' => $ct['id']
                ];
                $sId = ScheduleModel::insertSchedule($schedule);
                if (empty($sId)) {
                    return Valid::addErrors([], 'class_schedule', 'class_create_schedule_failure');
                }
                $users = [];
                foreach ($class['students'] as $student) {
                    if ($student['status'] == ScheduleUserModel::STATUS_NORMAL) {
                        $price = $student['price'][$key] * 100;
                        $users[] = ['price' => $price, 'schedule_id' => $sId, 'user_id' => $student['user_id'], 'user_role' => $student['user_role'], 'status' => ScheduleModel::STATUS_BOOK, 'create_time' => $now, 'user_status' => ScheduleUserModel::STUDENT_STATUS_BOOK];
                    }
                }
                foreach ($class['teachers'] as $teacher) {
                    if ($teacher['status'] == ScheduleUserModel::STATUS_NORMAL) {
                        $users[] = ['price' => 0, 'schedule_id' => $sId, 'user_id' => $teacher['user_id'], 'user_role' => $teacher['user_role'], 'status' => ScheduleModel::STATUS_BOOK, 'create_time' => $now, 'user_status' => ScheduleUserModel::TEACHER_STATUS_SET];
                    }
                }
                $flag = ScheduleUserModel::insertSUs($users);
                if ($flag == false)
                    return Valid::addErrors([], 'class_schedule', 'class_create_schedule_failure');
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
    public static function getList($params, $page = -1, $count = 20)
    {
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
                    $result[$su['schedule_id']]['students'] ++;
                } else
                    $result[$su['schedule_id']]['teachers'] ++;
            }
        }
        // order
        $schedules = [];
        foreach ($sIds as $sId) {
            $schedules[] = $result[$sId];
        }
        return [$count, $schedules];
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
            if(!empty($su['balance'])) {
                $su['balance'] /= 100;
            }
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
        if(in_array($su['user_role'],  [ClassUserModel::USER_ROLE_T,ClassUserModel::USER_ROLE_HT])) {
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

        $studentRole = ScheduleUserModel::USER_ROLE_STUDENT;
        $teacherRole = [ScheduleUserModel::USER_ROLE_TEACHER, ScheduleUserModel::USER_ROLE_CLASS_TEACHER];
        $studentIds = ScheduleUserModel::getUserIds($newSchedule['id'], $studentRole);
        $teacherIds = ScheduleUserModel::getUserIds($newSchedule['id'], $teacherRole);

        // 学员已付费，不能解绑
        // unbind schedule_user
        ScheduleUserService::unBindUser($newSchedule['id'], array_diff($studentIds, array_keys($params['students'])), $studentRole, $time);
        ScheduleUserService::unBindUser($newSchedule['id'], array_diff($teacherIds, array_keys($params['teachers'])), $teacherRole, $time);

        foreach ($params['students'] as $key => $value) {
            if (in_array($key, $studentIds)) {
                // update schedule_user price
                ScheduleUserService::updateSchedulePrice($newSchedule['id'], $key, $value[0] * 100, $time);
                unset($params['students'][$key]);
            }
        }
        foreach ($params['teachers'] as $key => $value) {
            if (in_array($key, $teacherIds)) {
                unset($params['teachers'][$key]);
            }
        }

        // add schedule_user
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
     * @param $ctIds
     * @return bool
     */
    public static function bindSUs($stId, $users, $userRole, $ctIds = null)
    {
        $sus = [];
        $now = time();
        list($count, $schedules) = self::getList(['class_id' => $stId, 'status' => ScheduleModel::STATUS_BOOK]);
        foreach ($schedules as $schedule) {
            foreach ($users as $userId => $value) {
                if ($userRole == ClassUserModel::USER_ROLE_S) {
                    $key = array_search($schedule['c_t_id'], $ctIds);
                    $price = $value[$key] * 100;
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

    /**
     * 学员上课记录 1对1
     * @param $orgId
     * @param $page
     * @param $count
     * @param $params
     * @return array|string
     */
    public static function AIAttendRecord($orgId, $page, $count, $params)
    {
        $whole = [];

        list($records, $total) = ScheduleModel::AIAttendRecord($orgId, $page, $count, $params);

        foreach ($records as &$r) {
            $r['status']   = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_STATUS, $r['status']);
            $r['duration'] = Util::formatExerciseTime($r['duration']);
            if(!empty($r['detail_score'])) {
                $detail = json_decode($r['detail_score'], true);
                $r['homework_rank'] = ScheduleExtendModel::$homework_score_map[$detail['homework_rank']]; //作业评价
                $r['performance_rank'] = ScheduleExtendModel::$performance_score_map[$detail['performance_rank']]; //课堂评价
            }
            if(!empty($r['opn_lessons'])) {
                $opnLessonArray = explode(',', $r['opn_lessons']);
                $whole = array_merge($whole, $opnLessonArray);
            }
            $r['teacher_name'] = $r['teacher_name'] . '(' . $r['teacher_mobile'] . ')';
            $r['student_name'] = $r['student_name'] . '(' . $r['student_mobile'] . ')';
            $r['opn_lessons_info'] = '';
        }
        //将所有曲谱id去重后发起一次网络请求
        if(count($whole) > 0) {
            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_TEACHER, 1);
            $result = $opn->lessonsByIds(array_unique($whole));
            if(empty($result) || $result['code'] != Valid::CODE_SUCCESS) {
                return [$records, $total];
            }
            $opnMap = [];
            foreach($result['data'] as $one) {
                $opnMap[$one['id']] = $one;
            }
            //每条记录按需要提取曲谱信息
            foreach($records as &$r) {
                $opnArray = explode(',', $r['opn_lessons']);
                if(count($opnArray) > 0) {
                    $info = [];
                    foreach($opnArray as $id) {
                        if(isset($opnMap[$id])) {
                            $info[] = $opnMap[$id]['name'];
                        }
                    }
                    $r['opn_lessons_info'] = implode(',', $info);
                }
            }
        }

        return [$records, $total];
    }

    public static function attendRecord($orgId, $page, $count, $params)
    {
        list($records, $total) = ScheduleModel::attendRecord($orgId, $page, $count, $params);

        foreach ($records as &$r) {
            $r['status']   = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_STATUS, $r['status']);
            $r['duration'] = Util::formatExerciseTime($r['duration']);
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
        $class['name'] = $params['class_name'];
        $class['campus_id'] = $classroom['campus_id'];
        $class['org_id'] = $classroom['org_id'];
        $class['class_lowest'] = 1;
        $class['class_highest'] = $params['class_highest'];
        $class['create_time'] = $time;
        $class['status'] = STClassModel::STATUS_BEGIN;
        $class['student_num'] = count($params['students']);

        // 创建class, class_task, class_user
        $classId = STClassService::addSTClass($class, $classTasks, $params['students'], $params['teachers']);
        if (empty($classId)) {
            return Valid::addErrors([], 'class_failure', 'class_add_failure');
        }

        $ctId = ClassTaskService::getCTIds($classId);
        $schedule['c_t_id'] = $ctId[0];
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
     * @return array|bool
     */
    public static function checkScheduleAndClassTask($params, $schedule, $time)
    {
        if (empty($params['students']) || !is_array($params['students'])) {
            return Valid::addErrors([], 'students', 'students_is_required');
        }
        if (empty($params['teachers']) || !is_array($params['teachers'])) {
            return Valid::addErrors([], 'teachers', 'teacher_is_required');
        }

        $classroom = ClassroomService::getById($params['classroom_id']);
        $course = CourseService::getCourseById($params['course_id']);
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

        // check schedule, check schedule_user
        $checkSchedule = self::checkSchedule($schedule);
        if ($checkSchedule !== true) {
            return Valid::addErrors([], 'schedule', 'check_schedule_error');
        }

        $originScheduleId = $params['schedule_id'] ?? 0;
        $checkStudent = ScheduleUserService::checkScheduleUser(array_keys($params['students']),
            ScheduleUserModel::USER_ROLE_STUDENT, $schedule['start_time'],
            $schedule['end_time'], $originScheduleId);
        if ($checkStudent !== true) {
            return Valid::addErrors([], 'class_task_classroom', 'class_student_time_error');
        }
        $balances = StudentAccountService::checkBalance($params['students'], [$classTask]);
        if ($balances !== true) {
            return $balances;
        }

        $checkTeacher = ScheduleUserService::checkScheduleUser(array_keys($params['teachers']),
            [ScheduleUserModel::USER_ROLE_TEACHER, ScheduleUserModel::USER_ROLE_CLASS_TEACHER],
            $schedule['start_time'], $schedule['end_time'], $originScheduleId);
        if ($checkTeacher !== true) {
            return Valid::addErrors([], 'class_task_classroom', 'class_teacher_time_error');
        }

        // check class_task, class_user
        $classStatus = STClassModel::STATUS_NORMAL;
        $checkRes = ClassTaskService::checkCT($classTask, $classStatus);
        if ($checkRes !== true) {
            return Valid::addErrors([], 'class_task_classroom', 'class_task_classroom_error');
        }

        $classTasks[] = $classTask;
        $checkStudent = ClassUserService::checkStudent($params['students'], $classTasks, $params['class_highest'], $classStatus);
        if ($checkStudent !== true) {
            return $checkStudent;
        }
        $checkTeacher = ClassUserService::checkTeacher($params['teachers'], $classTasks, [], $classStatus);
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

    /**
     * @param $campusId
     * @param $courseId
     * @return array
     * 当前机构校区的所有教室对于当前课程的排课时间安排
     */
    public static function getOrgCampusArrage($campusId, $courseId)
    {
        $result = ClassTaskModel::getOrgCampusArrange($campusId, $courseId);
        $classInfo = NULL;
        //满足此课程的排课在未来一个月内的可上课时间安排
        if (!empty($result)) {
            $classInfo = array_column($result, NULL,'class_id');
        }
        return $classInfo;
    }

    /**
     * 导出课消数据
     * @param $startTime
     * @param $endTime
     * @return mixed
     */
    public static function selectFinishedSchedules($startTime, $endTime)
    {
        $schedules = ScheduleModel::selectFinishedSchedules($startTime, $endTime);
        $data[0] = [
            '订单编号', '签约人', '学员', '套餐名称', '签约日期', '课时单价',
            '上课日期', '上课时间', '上课校区', '上课节数', '消课金额', '操作人', '消课日期', '已消课金额', '剩余课时金额'
        ];
        $data[0] = array_map(function ($val) {
            return iconv("utf-8","GB18030//IGNORE", $val);
        }, $data[0]);

        $i = 1;
        foreach ($schedules as $key => $schedule) {
            $billOperator = !empty($schedule['bill_operator']) ? explode(',', $schedule['bill_operator']) : [];
            $billOperator = !empty($billOperator) ? $billOperator[0] : '';

            // 订单编号, 签约人, 学员姓名
            $data[$i][0] = iconv("utf-8","GB18030//IGNORE", "\t" . $schedule['bill_ids']);
            $data[$i][1] = iconv("utf-8","GB18030//IGNORE", $billOperator);
            $data[$i][2] = iconv("utf-8","GB18030//IGNORE", $schedule['student_name']);

            // 套餐名称, 签约日期
            $data[$i][3] = iconv("utf-8","GB18030//IGNORE", $schedule['course_name']);
            $data[$i][4] = iconv("utf-8","GB18030//IGNORE", "\t" . date('Y-m-d H:i:s', $schedule['bill_time']));

            // 课时单价, 上课日期, 上课时间, 上课校区
            $data[$i][5] = iconv("utf-8","GB18030//IGNORE", "\t" . $schedule['price'] / 100);
            $data[$i][6] = iconv("utf-8","GB18030//IGNORE", "\t" . date('Y-m-d', $schedule['start_time']));
            $data[$i][7] = iconv("utf-8","GB18030//IGNORE", "\t" . date('H:i', $schedule['start_time']) . ' - ' . date('H:i', $schedule['end_time']));
            $data[$i][8] = iconv("utf-8","GB18030//IGNORE", $schedule['campus_name']);
            // 上课节数, 消课金额, 操作人, 消课日期, 已消课金额, 剩余课时金额
            $data[$i][9] = iconv("utf-8","GB18030//IGNORE", 1);
            $data[$i][10] = iconv("utf-8","GB18030//IGNORE", "\t" . $schedule['reduce_num'] / 100);
            $data[$i][11] = iconv("utf-8","GB18030//IGNORE", $schedule['operator_name']);
            $data[$i][12] = iconv("utf-8","GB18030//IGNORE", "\t" . date('Y-m-d H:i:s', $schedule['reduce_time']));
            $data[$i][13] = iconv("utf-8","GB18030//IGNORE", "\t" . ($schedule['total_amount'] - $schedule['new_balance']) / 100);
            $data[$i][14] = iconv("utf-8","GB18030//IGNORE", "\t" . $schedule['new_balance'] / 100);

            $i ++;
        }
        return $data;
    }

    /**
     * 获取学生课程金额，状态预约成功，且没有扣费
     * @param $studentIds
     * @return array
     */
    public static function getTakeUpScheduleBalances($studentIds)
    {
        return ScheduleModel::getTakeUpStudentBalances($studentIds);
    }
}