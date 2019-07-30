<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/21
 * Time: 2:55 PM
 */

namespace App\Routers;


use App\Controllers\StudentWX\PlayRecordForPanda;
use App\Controllers\StudentWX\Student;
use App\Middleware\WeChatAuthCheckMiddleware;
use App\Middleware\WeChatPandaAuthCheckMiddleware;

class StudentWXRouter extends RouterBase
{
    protected $logFilename = 'dss_student_wx.log';
    public $middleWares = [WeChatAuthCheckMiddleware::class];
    protected $uriConfig = [
        '/student_wx/student/register' => array('method'=>array('post'),'call'=>'\App\Controllers\StudentWX\Student:register', 'middles' => array('\App\Middleware\WeChatOpenIdCheckMiddleware')),
        '/student_wx/student/login' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Student:login', 'middles' => array('\App\Middleware\WeChatOpenIdCheckMiddleware')),
        '/student_wx/student/send_sms_code' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Student:sendSmsCode', 'middles' => array()),
        '/student_wx/student/account_detail' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Student:accountDetail'),
        '/student_wx/student/edit_account' => array('method'=>array('post'),'call'=>'\App\Controllers\StudentWX\Student:editAccountInfo'),
        '/student_wx/student/day_report' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:recordReport'),
        '/student_wx/student/shared_report' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:shareReport', 'middles' => array()),
        '/student_wx/schedule/schedule_report' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Schedule:scheduleReport'),
        '/student_wx/common/js_config' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Common:getJsConfig', 'middles' => array()),
        '/student_wx/play_record/homework_demand' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getHomeworkDemand', 'middles' => array()),
        '/student_wx/play_record/wonderful_moment' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getWonderfulMomentUrl'),
        '/student_wx/play_record/share_wonderful_moment' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:shareWonderfulMomentUrl', 'middles' => array()),
        '/student_wx/play_record/play_record_list' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getPlayRecordList'),
        '/student_wx/play_record/share_play_record_list' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:sharePlayRecordList', 'middles' => array()),
        '/student_wx/schedule/schedule_list' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Schedule:scheduleList'),
        '/student_wx/play_record/test_statistics' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getLessonTestStatistics'),
        '/student_wx/play_record/share_test_statistics' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:shareLessonTestStatistics', 'middles' => array()),
        '/student_wx/play_record/ai_record_grade' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getAIRecordGrade'),
        '/student_wx/play_record/share_ai_record_grade' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:shareAIRecordGrade', 'middles' => array()),
        '/student_wx/play_record/month_statistic' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getMonthStatistics'),
        '/student_wx/play_record/month_day_statistic' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getMonthDayStatistics'),

        '/student_wx/student/gift_code' => [
            'method' => ['get'],
            'call' => Student::class . ':giftCode',
        ],

        '/student_org_wx/student/register' => array('method'=>array('post'),'call'=>'\App\Controllers\StudentOrgWX\Student:register', 'middles' => array('\App\Middleware\WeChatOpenIdCheckMiddleware')),
        '/student_org_wx/student/login' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentOrgWX\Student:login', 'middles' => array('\App\Middleware\WeChatOpenIdCheckMiddleware')),
        '/student_org_wx/student/send_sms_code' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentOrgWX\Student:sendSmsCode', 'middles' => array()),
        '/student_org_wx/callback/check' => array('method'=>array('get', 'post'),'call'=>'\App\Controllers\StudentOrgWX\Callback:weChatCallback', 'middles' => array()),
        '/student_org_wx/course/get_test_course' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentOrgWX\Course:getTestCourse'),
        '/student_org_wx/org_campus/get_org_campus' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentOrgWX\OrgCampus:getOrgCampusList'),
        '/student_org_wx/org_campus/get_org_campus_arrange' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentOrgWX\OrgCampus:getOrgCampusArrange'),
        '/student_org_wx/student_class/order_class' => array('method'=>array('post'),'call'=>'\App\Controllers\StudentOrgWX\StudentClass:OrderClass'),
        '/student_org_wx/student_class/get_order_class' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentOrgWX\StudentClass:getOrderClass'),

        // 小叶子(熊猫)陪练微信 数据接口
        '/student_panda_wx/play_record/month_statistic' => [
            'method' => ['get'],
            'call' => PlayRecordForPanda::class . ':getMonthStatistics',
            'middles' => [WeChatPandaAuthCheckMiddleware::class]
        ],
        '/student_panda_wx/play_record/month_day_statistic' => [
            'method' => ['get'],
            'call' => PlayRecordForPanda::class . ':getMonthDayStatistics',
            'middles' => [WeChatPandaAuthCheckMiddleware::class]
        ],
    ];
}