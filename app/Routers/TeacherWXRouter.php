<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/21
 * Time: 2:56 PM
 */

namespace App\Routers;


class TeacherWXRouter extends RouterBase
{
    protected $logFilename = 'dss_teacher_wx.log';

    protected $uriConfig = [
        '/teacher_wx/student/list' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Student:get', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
        '/teacher_wx/homework/collection_list' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getRecentCollections', 'middles' => array( '\App\Middleware\WeChatAuthCheckMiddleware')),
        '/teacher_wx/homework/lesson_list' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getRecentLessons', 'middles' => array( '\App\Middleware\WeChatAuthCheckMiddleware')),
        '/teacher_wx/homework/search_collections' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:searchCollections', 'middles' => array( '\App\Middleware\WeChatAuthCheckMiddleware')),
        '/teacher_wx/homework/search_lessons' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:searchLessons', 'middles' => array( '\App\Middleware\WeChatAuthCheckMiddleware')),
        '/teacher_wx/homework/lessons' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getLessons', 'middles' => array( '\App\Middleware\WeChatAuthCheckMiddleware')),
        '/teacher_wx/homework/homework_demand' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getHomeworkDemand', array()),
        '/teacher_wx/homework/add' => array('method'=>array('post'),'call'=>'\App\Controllers\TeacherWX\Homework:add', 'middles' => array( '\App\Middleware\WeChatAuthCheckMiddleware')),
        '/teacher_wx/teacher/register' => array('method'=>array('post'),'call'=>'\App\Controllers\TeacherWX\Teacher:register', 'middles' => array('\App\Middleware\WeChatOpenIdCheckMiddleware')),
        '/teacher_wx/teacher/login' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Teacher:login', 'middles' => array('\App\Middleware\WeChatOpenIdCheckMiddleware')),
        '/teacher_wx/homework/play_record_list' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getHomeworkPlayRecordList', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
        '/teacher_wx/homework/homework_record_detail' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getTaskDetail', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
        '/teacher_wx/homework/ai_record_grade' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Homework:getAIRecordGrade', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
        '/teacher_wx/teacher/send_sms_code' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Teacher:sendSmsCode', 'middles' => array()),
        '/teacher_wx/teacher/teacher_org_list' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Teacher:getBindOrgList', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
        '/teacher_wx/teacher/generate_invite_qr_code' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Teacher:getInviteQrCode', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
        '/teacher_wx/schedule/student_schedule_list' => array('method'=>array('get'),'call'=>'\App\Controllers\TeacherWX\Schedule:scheduleList', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
    ];

}