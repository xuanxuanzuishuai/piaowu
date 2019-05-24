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

class TeacherWXRouter extends RouterBase
{
    protected $logFilename = 'dss_teacher_wx.log';
    public $middleWares = [WeChatAuthCheckMiddleware::class];
    protected $uriConfig = [
        '/teacher_wx/student/list' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Student:get'),
        '/teacher_wx/homework/collection_list' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Homework:getRecentCollections'),
        '/teacher_wx/homework/lesson_list' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Homework:getRecentLessons'),
        '/teacher_wx/homework/search_collections' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Homework:searchCollections'),
        '/teacher_wx/homework/search_lessons' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Homework:searchLessons'),
        '/teacher_wx/homework/lessons' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Homework:getLessons'),
        '/teacher_wx/homework/homework_demand' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Homework:getHomeworkDemand', array()),
        '/teacher_wx/homework/add' => array('method' => array('post'), 'call' => '\App\Controllers\TeacherWX\Homework:add'),
        '/teacher_wx/teacher/register' => array('method' => array('post'), 'call' => '\App\Controllers\TeacherWX\Teacher:register'),
        '/teacher_wx/teacher/login' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Teacher:login'),
        '/teacher_wx/homework/play_record_list' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Homework:getHomeworkPlayRecordList'),
        '/teacher_wx/homework/homework_record_detail' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Homework:getTaskDetail'),
        '/teacher_wx/homework/ai_record_grade' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Homework:getAIRecordGrade'),
        '/teacher_wx/teacher/send_sms_code' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Teacher:sendSmsCode', 'middles' => array()),
        '/teacher_wx/teacher/teacher_org_list' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Teacher:getBindOrgList'),
        '/teacher_wx/teacher/generate_invite_qr_code' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Teacher:getInviteQrCode'),
        '/teacher_wx/schedule/student_schedule_list' => array('method' => array('get'), 'call' => '\App\Controllers\TeacherWX\Schedule:scheduleList'),
    ];

    public function getMiddleWares($uri)
    {
        if(in_array($uri,['/teacher_wx/teacher/register','/teacher_wx/teacher/login'])) {
            return [WeChatOpenIdCheckMiddleware::class];
        }
        return $this->middleWares;
    }

}