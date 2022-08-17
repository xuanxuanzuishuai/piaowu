<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/10/19
 * Time: 11:40
 */
namespace App\Routers;

use App\Controllers\Agent\Agent;
use App\Controllers\ReferralMiniapp\Landing;
use App\Controllers\ReferralMiniapp\Pay;
use App\Controllers\StudentWX\Student;
use App\Middleware\ReferralMinAppAuthCheckMiddleware;

class ReferralMinAppRouter extends RouterBase
{
    protected $logFilename = 'operation_referral_miniapp.log';

    protected $uriConfig = [
        '/referral_miniapp/landing/send_sms_code' => [
            'method'  => ['get'],
            'call'    => Student::class.':sendSmsCode',
            'middles' => []
        ],
        '/referral_miniapp/landing/country_code' => [
            'method'  => ['get'],
            'call'    => Agent::class . ':countryCode'
        ],
         '/referral_miniapp/landing/register' => [
             'method'  => ['post'],
             'call'    => Landing::class . ':register',
             'middles' => [ReferralMinAppAuthCheckMiddleware::class],
         ],
        '/referral_miniapp/landing/tf_register' => [
            'method'  => ['post'],
            'call'    => Landing::class . ':tfregister',
            'middles' => [ReferralMinAppAuthCheckMiddleware::class],
        ],
         '/referral_miniapp/landing/index' => [
             'method'  => ['get'],
             'call'    => Landing::class . ':index',
             'middles' => [ReferralMinAppAuthCheckMiddleware::class],
         ],
        '/referral_miniapp/landing/create_bill' => [
            'method'  => ['get', 'post'],
            'call'    => Pay::class . ':createBill'
        ],
        '/referral_miniapp/landing/buy_name' => [
            'method'  => ['get', 'post'],
            'call'    => Landing::class . ':buyName',
            'middles' => [ReferralMinAppAuthCheckMiddleware::class]
        ],
        '/referral_miniapp/landing/buy_poster' => [
            'method'  => ['get'],
            'call'    => Landing::class . ':buyPageReferralPoster',
            'middles' => [ReferralMinAppAuthCheckMiddleware::class],
        ],
        '/referral_miniapp/landing/play_review' => [
            'method'  => ['get'],
            'call'    => Landing::class . ':playReview',
            'middles' => [ReferralMinAppAuthCheckMiddleware::class],
        ],
        '/referral_miniapp/landing/assistant_info' => [
            'method'  => ['get'],
            'call'    => Landing::class . ':assistantInfo',
            'middles' => [ReferralMinAppAuthCheckMiddleware::class],
        ],
        //  获取学生是否是系统判定的重复用户，如果是购买指定课包时会返回其他课包
        '/referral_miniapp/student/check_student_is_repeat' => [
            'method' => ['get'],
            'call' => Landing::class . ':checkStudentIsRepeat',
            'middles' => [ReferralMinAppAuthCheckMiddleware::class]
        ],
        //用户支付成功获取跳转小程序加微页链接
        '/referral_miniapp/url_scheme/get_assistant_wx_url' => [
            'method'  => ['get'],
            'call'    => Landing::class . ':getAssistantWxUrl',
            'middles' => [],
        ],
    ];

}