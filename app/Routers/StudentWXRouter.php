<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/21
 * Time: 2:55 PM
 */

namespace App\Routers;


class StudentWXRouter extends RouterBase
{
    protected $logFilename = 'dss_student_wx.log';

    protected $uriConfig = [
        '/student_wx/student/register' => array('method'=>array('post'),'call'=>'\App\Controllers\StudentWX\Student:register', 'middles' => array('\App\Middleware\WeChatOpenIdCheckMiddleware')),
        '/student_wx/student/login' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Student:login', 'middles' => array('\App\Middleware\WeChatOpenIdCheckMiddleware')),
        '/student_wx/student/send_sms_code' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Student:sendSmsCode', 'middles' => array()),
        '/student_wx/student/account_detail' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Student:accountDetail', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
        '/student_wx/student/edit_account' => array('method'=>array('post'),'call'=>'\App\Controllers\StudentWX\Student:editAccountInfo', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
        '/student_wx/student/day_report' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:recordReport', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
        '/student_wx/student/shared_report' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:shareReport', 'middles' => array()),
        '/student_wx/schedule/schedule_report' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Schedule:scheduleReport', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
        '/student_wx/common/js_config' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Common:getJsConfig', 'middles' => array()),
        '/student_wx/play_record/homework_demand' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getHomeworkDemand', 'middles' => array()),
        '/student_wx/play_record/wonderful_moment' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getWonderfulMomentUrl', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
        '/student_wx/play_record/share_wonderful_moment' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:shareWonderfulMomentUrl', 'middles' => array()),
        '/student_wx/play_record/play_record_list' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getPlayRecordList', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
        '/student_wx/play_record/share_play_record_list' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:sharePlayRecordList', 'middles' => array()),
        '/student_wx/schedule/schedule_list' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Schedule:scheduleList', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
        '/student_wx/play_record/test_statistics' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getLessonTestStatistics', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
        '/student_wx/play_record/share_test_statistics' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:shareLessonTestStatistics', 'middles' => array()),
        '/student_wx/play_record/ai_record_grade' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getAIRecordGrade', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
        '/student_wx/play_record/share_ai_record_grade' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:shareAIRecordGrade', 'middles' => array()),
        '/student_wx/play_record/month_statistic' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getMonthStatistics', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),
        '/student_wx/play_record/month_day_statistic' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getMonthDayStatistics', 'middles' => array('\App\Middleware\WeChatAuthCheckMiddleware')),

        '/student_org_wx/student/register' => array('method'=>array('post'),'call'=>'\App\Controllers\StudentOrgWX\Student:register', 'middles' => array('\App\Middleware\WeChatOpenIdCheckMiddleware')),
        '/student_org_wx/student/login' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentOrgWX\Student:login', 'middles' => array('\App\Middleware\WeChatOpenIdCheckMiddleware')),
        '/student_org_wx/student/send_sms_code' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentOrgWX\Student:sendSmsCode', 'middles' => array()),
        '/student_org_wx/callback/check' => array('method'=>array('get', 'post'),'call'=>'\App\Controllers\StudentOrgWX\Callback:weChatCallback', 'middles' => array()),

    ];

}