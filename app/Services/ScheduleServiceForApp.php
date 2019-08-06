<?php
/**
 * Created by PhpStorm.
 * User: dahua
 * Date: 2019-04-24
 * Time: 16:11
 */

namespace App\Services;

use App\Libs\DictConstants;
use App\Models\ScheduleExtendModel;
use App\Models\ScheduleModelForApp;
use App\Models\ScheduleUserModel;

class ScheduleServiceForApp
{
    /**
     * 1v1课程结束，我们在此时创建课程
     * @param $schedule
     * @return array
     */
    public static function endSchedule($schedule){
        // 插入schedule
        $now = time();
        $courseId = DictConstants::get(DictConstants::APP_CONFIG_TEACHER, 'course_id');

        $_schedule = [
            'course_id' => $courseId,
            'start_time' => $schedule['start_time'],
            'end_time' => $now,
            'duration' => $now - $schedule['start_time'],
            'status' => -1,
            'org_id' => $schedule['org_id'],
            'create_time' => $now,
            'update_time' => $now,
        ];
        $scheduleId = ScheduleModelForApp::insertRecord($_schedule);
        if(!$scheduleId){
            return ['insert_schedule_fail', null];
        }

        // 插入schedule_user
        $schedule_user = [
            'schedule_id' => $scheduleId,
            'create_time' => $now,
            'update_time' => $now,
            'status' => 1,
            'remark' => ''
        ];
        $schedule_student = $schedule_user;
        $schedule_student['user_id'] = $schedule['student_id'];
        $schedule_student['user_role'] = 1;
        $schedule_student['user_status'] = ScheduleUserModel::STUDENT_STATUS_ATTEND;
        $schedule_teacher = $schedule_user;
        $schedule_teacher['user_id'] = $schedule['teacher_id'];
        $schedule_teacher['user_role'] = 2;
        $schedule_teacher['user_status'] = ScheduleUserModel::TEACHER_STATUS_ATTEND;
        $scheduleUsers = [$schedule_student, $schedule_teacher];
        $scheduleUserOk = ScheduleUserModel::batchInsert($scheduleUsers, false);
        if(!$scheduleUserOk){
            return ['insert_schedule_user_fail', null];
        }

        // 处理课后单
        $report = [];
        $report['schedule_id'] = $scheduleId;
        $report['course_id'] = $courseId;
        $report['opn_lessons'] = implode(",", $schedule['lessons']);
        $report['remark'] = $schedule['report']['remark'];
        $report['audio_comment'] = $schedule['report']['audio_comment'];
        $report['class_score'] = $schedule['report']['class_score'];
        $report['detail_score'] = json_encode([
            'homework_rank' => $schedule['report']['homework_rank'],
            'performance_rank' => $schedule['report']['performance_rank']
        ]);
        $reportOk = ScheduleExtendModel::insertReport($report);
        if(!$reportOk){
            return ['insert_report_fail', null];
        }
        return [null, $scheduleId];
    }
}