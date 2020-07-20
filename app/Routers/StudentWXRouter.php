<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/21
 * Time: 2:55 PM
 */

namespace App\Routers;


use App\Controllers\StudentWX\PlayReport;
use App\Controllers\StudentWX\Area;
use App\Controllers\StudentWX\Pay;
use App\Controllers\StudentWX\Referral;
use App\Controllers\StudentWX\ReviewCourse;
use App\Controllers\StudentWX\PlayRecord;
use App\Controllers\StudentWX\PlayRecordForPanda;
use App\Controllers\StudentWX\Student;
use App\Middleware\WeChatAuthCheckMiddleware;
use App\Middleware\WeChatPandaAuthCheckMiddleware;
use App\Controllers\StudentWX\ReferralActivity;
use App\Controllers\StudentWX\TemplatePoster;

class StudentWXRouter extends RouterBase
{
    protected $logFilename = 'dss_student_wx.log';
    public $middleWares = [WeChatAuthCheckMiddleware::class];
    protected $uriConfig = [

        /** 公共 */

        // 小叶子智能陪练微信公众号微信服务器回调地址
        '/student_wx/callback/check' => array('method'=>array('get', 'post'),'call'=>'\App\Controllers\StudentWX\Callback:weChatCallback', 'middles' => array()),

        '/student_wx/common/js_config' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Common:getJsConfig', 'middles' => array()),

        /** 用户信息 */

        '/student_wx/student/register' => array('method'=>array('post'),'call'=>'\App\Controllers\StudentWX\Student:register', 'middles' => array()),
        '/student_wx/student/login' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Student:login', 'middles' => array('\App\Middleware\WeChatOpenIdCheckMiddleware')),
        '/student_wx/student/send_sms_code' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Student:sendSmsCode', 'middles' => array()),
        '/student_wx/student/account_detail' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Student:accountDetail'),
        '/student_wx/student/edit_account' => array('method'=>array('post'),'call'=>'\App\Controllers\StudentWX\Student:editAccountInfo'),

        '/student_wx/student/unbind' => array('method' => ['post'], 'call' => Student::class . ':unbind'),
        // 学生地址
        '/student_wx/student/address_list' => array('method' => ['get'], 'call' => Student::class . ':addressList'),
        '/student_wx/student/modify_address' => array('method' => ['post'], 'call' => Student::class . ':modifyAddress'),

        '/student_wx/area/get_by_parent_code' => array('method' => ['get'], 'call' => Area::class . ':getByParentCode'),
        '/student_wx/area/get_by_code' => array('method' => ['get'], 'call' => Area::class . ':getByCode'),

        // 订单
        '/student_wx/pay/create_bill' => array('method' => ['post'], 'call' => Pay::class . ':createBill'),
        '/student_wx/pay/bill_status' => array('method' => ['get'], 'call' => Pay::class . ':billStatus'),
        '/student_wx/pay/get_package_detail' => array('method' => ['get'], 'call' => Pay::class . ':getPackageDetail'),

        // 获取激活码信息
        '/student_wx/student/gift_code' => [
            'method' => ['get'],
            'call' => Student::class . ':giftCode',
        ],

        /** 演奏记录 */

        // 练琴月历(5.0作废)
        '/student_wx/play_record/play_calendar' => [
            'method' => ['get'],
            'call' => PlayRecord::class . ':playCalendar',
        ],

        // 日报(5.0作废)

        '/student_wx/student/day_report' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:recordReport'),
        '/student_wx/student/shared_report' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:shareReport', 'middles' => array()),

        // 单曲目单日演奏记录(5.0作废)
        '/student_wx/play_record/test_statistics' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:getLessonTestStatistics'),
        '/student_wx/play_record/share_test_statistics' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\PlayRecord:shareLessonTestStatistics', 'middles' => array()),

        // 练琴月历
        '/student_wx/play_report/play_calendar' => [
            'method' => ['get'],
            'call' => PlayReport::class . ':playCalendar',
        ],

        // 日报
        '/student_wx/play_report/day_report' => [
            'method' => ['get'],
            'call' => PlayReport::class . ':dayReport',
        ],

        // 日报(分享)
        '/student_wx/play_report/shared_day_report' => [
            'method' => ['get'],
            'call' => PlayReport::class . ':sharedDayReport',
            'middles' => []
        ],

        //测评结果（分享）
        '/student_wx/play_report/shared_assess_result' => [
            'method' => ['get'],
            'call' => PlayReport::class . ':sharedAssessResult',
            'middles' => []
        ],

        // 曲目单日测评成绩单
        '/student_wx/play_report/lesson_test_report' => [
            'method' => ['get'],
            'call' => PlayReport::class . ':lessonTestReport',
        ],

        // 曲目单日测评成绩单(分享)
        '/student_wx/play_report/shared_lesson_test_report' => [
            'method' => ['get'],
            'call' => PlayReport::class . ':sharedLessonTestReport',
            'middles' => []
        ],

        // ???
        '/student_wx/play_record/get_shared_report' => [
            'method' => ['get'],
            'call' => PlayRecord::class . ':getShareReport',
            'middles' => []
        ],

        // 点评
        '/student_wx/review_course/get_review' => [
            'method' => ['get'],
            'call' => ReviewCourse::class . ':getReview',
        ],

        '/student_wx/review_course/get_task_review' => [
            'method' => ['get'],
            'call' => ReviewCourse::class . ':getTaskReview',
        ],

        /** 熊猫 演奏记录 */

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

        /** 转介绍 */

        '/student_wx/referral/user_award_list' => [
            'method' => ['get'],
            'call' => Referral::class . ':userAwardList',
        ],

        '/student_wx/referral/user_referred_list' => [
            'method' => ['get'],
            'call' => Referral::class . ':userReferredList',
        ],
        '/student_wx/referral/activity_info' => [
            'method' => ['get'],
            'call' => ReferralActivity::class . ':activityInfo',
        ],
        '/student_wx/referral/upload_share_poster' => [
            'method' => ['post'],
            'call' => ReferralActivity::class . ':uploadSharePoster',
        ],
        '/student_wx/referral/join_record_list' => [
            'method' => ['get'],
            'call' => ReferralActivity::class . ':joinRecordList',
        ],
        '/student_wx/referral/cash_activity_info' => [
            'method' => ['get'],
            'call' => ReferralActivity::class . ':returnCashActivityInfo',
        ],
        '/student_wx/referral/upload_cash_poster' => [
            'method' => ['post'],
            'call' => ReferralActivity::class . ':uploadReturnCashPoster',
        ],
        '/student_wx/template_poster/poster' => [
            'method' => ['get'],
            'call' => TemplatePoster::class . ':templatePosterList',
        ],
        '/student_wx/template_poster/share_word' => [
            'method' => ['get'],
            'call' => TemplatePoster::class . ':posterShareWordList',
        ],
        '/student_wx/student/class_information' => [
            'method' => ['get'],
            'call' => Student::class . ':classInformation',
        ],
    ];
}