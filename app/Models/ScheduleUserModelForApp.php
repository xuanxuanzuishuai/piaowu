<?php
/**
 * Created by PhpStorm.
 * User: dahua
 * Date: 2019/5/9
 * Time: 15:09
 */


namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\DictConstants;

class ScheduleUserModelForApp extends Model
{
    public static $table = "schedule_user";
    public static $redisPri = "schedule_user";

    const STATUS_CANCEL = 0; //废除
    const STATUS_NORMAL = 1; //正常

    const USER_ROLE_STUDENT = 1; //学生
    const USER_ROLE_TEACHER = 2; //老师
    const USER_ROLE_CLASS_TEACHER = 3; // 班主任

    // 学生子状态
    const STUDENT_STATUS_BOOK = 1;         // 已预约
    const STUDENT_STATUS_CANCEL = 2;       // 已取消
    const STUDENT_STATUS_LEAVE = 3;        // 已请假
    const STUDENT_STATUS_ATTEND = 4;       // 已出席
    const STUDENT_STATUS_NOT_ATTEND = 5;   // 未出席
    // 老师子状态
    const TEACHER_STATUS_SET = 1;          // 已分配
    const TEACHER_STATUS_LEAVE = 2;        // 已请假
    const TEACHER_STATUS_ATTEND = 3;       // 已出席
    const TEACHER_STATUS_NOT_ATTEND = 4;   // 未出席

    public static function getUserSchedule($studentId, $teacherId, $where=[])
    {
        // FIXME
        // FILTER STUDENT OR TEACHER WITH MULTI ON CLAUSE INSTEAD
        // OF WHERE CLAUSE PREVENT FROM OCCURRING CARTESIAN PRODUCT
        $join = [
            '[><]'. ScheduleUserModel::$table . '(teacher)' => [
                'student.schedule_id' => 'schedule_id',
                //'student.user_role' => ScheduleUserModel::USER_ROLE_STUDENT,
                //'teacher.user_role' => ScheduleUserModel::USER_ROLE_TEACHER
            ],
            '[>]'. ScheduleModelForApp::$table . '(schedule_base)'  => [
                'student.schedule_id' => 'id'
            ],

            '[>]'. ScheduleExtendModel::$table . '(schedule_ext)' => [
                'student.schedule_id' => 'schedule_id'
            ],
        ];

        $where['schedule_ext.course_id'] = DictConstants::get(
            DictConstants::APP_CONFIG_TEACHER, 'course_id'
        );
        $where['student.user_role'] = ScheduleUserModel::USER_ROLE_STUDENT;
        $where['teacher.user_role'] = ScheduleUserModel::USER_ROLE_TEACHER;
        if (!empty($teacherId) and !empty($studentId)){
            //$join['[><]'. ScheduleUserModel::$table . 'teacher']['student.user_id'] = $studentId;
            //$join['[><]'. ScheduleUserModel::$table . 'teacher']['teacher.user_id'] = $teacherId;
            $where['student.user_id'] = $studentId;
            $where['teacher.user_id'] = $teacherId;

        }elseif(!empty($teacherId) and empty($studentId)){
            $where['teacher.user_id'] = $teacherId;
        }elseif (empty($teacherId) and !empty($studentId)){
            $where['student.user_id'] = $studentId;
        }else{
            return [];
        }

        $db = MysqlDB::getDB();
        $query = $db->select(
            ScheduleUserModel::$table . '(student)',
            $join,
            [
                'schedule_base.id',
                //'schedule_base.course_id',
                'schedule_base.start_time',
                'schedule_base.end_time',
                'schedule_base.duration',
                'schedule_base.create_time',
                'schedule_base.status',
                'schedule_base.org_id',
                'schedule_base.update_time',
                'schedule_ext.course_id (course_id_ext)',
                'schedule_ext.opn_lessons',
                'schedule_ext.detail_score',
                'schedule_ext.class_score',
                'schedule_ext.remark',
                'student.user_id student_id',
                'student.remark student_remark',
                'student.price student_price',
                'teacher.user_id teacher_id',
                'teacher.remark teacher_remark',
                'teacher.price teacher_price',
            ],
            $where
        );
        return $query;
    }
}