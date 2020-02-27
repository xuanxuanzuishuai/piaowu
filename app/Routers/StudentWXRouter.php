<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/21
 * Time: 2:55 PM
 */

namespace App\Routers;


use App\Controllers\StudentWX\Referral;
use App\Controllers\StudentWX\ReviewCourse;
use App\Controllers\StudentWX\PlayRecord;
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

        // TODO 删除练琴月历旧接口
        '/student_wx/play_record/month_statistic' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getMonthStatistics'),
        '/student_wx/play_record/month_day_statistic' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getMonthDayStatistics'),
        // 小叶子智能陪练微信公众号微信服务器回调地址
        '/student_wx/callback/check' => array('method'=>array('get', 'post'),'call'=>'\App\Controllers\StudentWX\Callback:weChatCallback', 'middles' => array()),

        // 练琴月历
        '/student_wx/play_record/play_calendar' => [
            'method' => ['get'],
            'call' => PlayRecord::class . ':playCalendar',
        ],

        '/student_wx/student/gift_code' => [
            'method' => ['get'],
            'call' => Student::class . ':giftCode',
        ],

        '/student_wx/play_record/get_shared_report' => [
            'method' => ['get'],
            'call' => PlayRecord::class . ':getShareReport',
            'middles' => []
        ],

        '/student_wx/review_course/get_review' => [
            'method' => ['get'],
            'call' => ReviewCourse::class . ':getReview',
        ],

        '/student_wx/review_course/get_task_review' => [
            'method' => ['get'],
            'call' => ReviewCourse::class . ':getTaskReview',
        ],

        '/student_wx/referral/user_award_list' => [
            'method' => ['get'],
            'call' => Referral::class . ':userAwardList',
        ],

        '/student_wx/referral/user_referred_list' => [
            'method' => ['get'],
            'call' => Referral::class . ':userReferredList',
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
        '/student_panda_wx/play_record/shared_report' => [
            'method' => ['get'],
            'call' => PlayRecordForPanda::class . ':shareReport',
            'middles' => []
        ],
        '/student_panda_wx/play_record/test_statistics' => [
            'method' => ['get'],
            'call' => PlayRecordForPanda::class . ':getLessonTestStatistics',
            'middles' => [WeChatPandaAuthCheckMiddleware::class]
        ],
        '/student_panda_wx/play_record/day_report' => [
            'method' => ['get'],
            'call' => PlayRecordForPanda::class . ':recordReport',
            'middles' => [WeChatPandaAuthCheckMiddleware::class]
        ],
        '/student_panda_wx/play_record/played_students' => [
            'method' => ['get'],
            'call' => PlayRecordForPanda::class . ':getDayPlayedStudents',
            'middles' => []
        ],
    ];
}