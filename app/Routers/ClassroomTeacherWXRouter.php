<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/21
 * Time: 2:56 PM
 */

namespace App\Routers;


use App\Middleware\WeChatAuthCheckMiddleware;
use App\Middleware\WeChatOpenIdCheckMiddleware;

//TheONE国际钢琴课公众号老师端
class ClassroomTeacherWXRouter extends RouterBase
{
    protected $logFilename = 'dss_classroom_teacher_wx.log';
    public $middleWares = [WeChatAuthCheckMiddleware::class];
    protected $uriConfig = [
        '/classroom_teacher_wx/student/list' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Student:get'),
        '/classroom_teacher_wx/class/list' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Clazz:list'),
        '/classroom_teacher_wx/class/student_list' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Clazz:studentList'),
        '/classroom_teacher_wx/homework/collection_list' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Homework:getRecentCollections'),
        '/classroom_teacher_wx/homework/lesson_list' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Homework:getRecentLessons'),
        '/classroom_teacher_wx/homework/search_collections' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Homework:searchCollections'),
        '/classroom_teacher_wx/homework/search_lessons' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Homework:searchLessons'),
        '/classroom_teacher_wx/homework/lessons' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Homework:getLessons'),
        '/classroom_teacher_wx/homework/homework_demand' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Homework:getHomeworkDemand', array()),
        '/classroom_teacher_wx/homework/add' => array('method' => array('post'), 'call' => '\App\Controllers\TeacherWX\Homework:add'),
        '/classroom_teacher_wx/teacher/register' => array('method' => array('post'), 'call' => '\App\Controllers\TeacherWX\Teacher:register'),
        '/classroom_teacher_wx/teacher/login' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Teacher:login'),
        '/classroom_teacher_wx/homework/play_record_list' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Homework:getHomeworkPlayRecordList'),
        '/classroom_teacher_wx/homework/homework_record_detail' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Homework:getTaskDetail'),
        '/classroom_teacher_wx/homework/ai_record_grade' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Homework:getAIRecordGrade'),
        '/classroom_teacher_wx/teacher/send_sms_code' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Teacher:sendSmsCode', 'middles' => array()),
        '/classroom_teacher_wx/teacher/teacher_org_list' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Teacher:getBindOrgList'),
        '/classroom_teacher_wx/teacher/generate_invite_qr_code' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Teacher:getInviteQrCode'),
        '/classroom_teacher_wx/schedule/student_schedule_list' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Schedule:scheduleList'),
    ];

    public function getMiddleWares($uri)
    {
        if(in_array($uri,['/classroom_teacher_wx/teacher/register','/classroom_teacher_wx/teacher/login'])) {
            return [WeChatOpenIdCheckMiddleware::class];
        }
        return $this->middleWares;
    }

}