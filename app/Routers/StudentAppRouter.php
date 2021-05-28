<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/16
 * Time: 3:52 PM
 */

namespace App\Routers;

use App\Controllers\StudentApp\App;
use App\Controllers\StudentApp\Auth;
use App\Controllers\StudentApp\DuanWuActivity;
use App\Controllers\StudentApp\Poster;
use App\Controllers\StudentWX\Activity;
use App\Controllers\StudentWX\Poster AS WXPoster;
use App\Controllers\StudentApp\ReferralActivity;
use App\Middleware\AppAuthMiddleWare;

class StudentAppRouter extends RouterBase
{
    protected $logFilename = 'operation_app.log';

    protected $uriConfig = [
        '/student_app/app/country_code' => [
            'method' => ['get'],
            'call' => App::class . ':countryCode',
        ],
        '/student_app/auth/getTokenByOtherToken' => [
            'method' => ['get'],
            'call' => Auth::class . ':getTokenByOtherToken',
        ],
        '/student_app/auth/accountDetail' => [
            'method' => ['get'],
            'call' => Auth::class . ':accountDetail',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/poster/getPoster' => [
            'method' => ['get'],
            'call' => Poster::class . ':templatePosterList',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/poster/getTemplateWord' => [
            'method' => ['get'],
            'call' => Poster::class . ':getTemplateWord',
            'middles' => [AppAuthMiddleWare::class]
        ],
        //周周有礼
        '/student_app/referral/activity_info' => [
            'method' => ['get'],
            'call' => ReferralActivity::class . ':activityInfo',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/referral/upload_share_poster' => [
            'method' => ['post'],
            'call'    => ReferralActivity::class . ':uploadSharePoster',
            'middles' => [AppAuthMiddleWare::class]

        ],
        '/student_app/referral/join_record_list' => [
            'method' => ['get'],
            'call' => ReferralActivity::class . ':joinRecordList',
            'middles' => [AppAuthMiddleWare::class]

        ],

        //端午节活动
        '/student_app/duanwu_activity/activity_info' => [
            'method' => ['get'],
            'call'   => DuanWuActivity::class . ':activityInfo',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/duanwu_activity/referee_list' => [
            'method' => ['get'],
            'call'   => DuanWuActivity::class . ':refereeList',
            'middles' => [AppAuthMiddleWare::class]
        ],

        // 月月有奖 && 周周领奖
        // 海报列表
        '/student_app/poster/list' => [
            'method' => ['get'],
            'call' => WXPoster::class . ':list',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/poster/upload' => [
            'method' => ['post'],
            'call' => WXPoster::class . ':upload',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/text/list' => [
            'method' => ['get'],
            'call' => WXPoster::class . ':wordList',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/activity/list' => [
            'method' => ['get'],
            'call' => Activity::class . ':weekActivityList',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/share_poster/list' => [
            'method' => ['get'],
            'call' => WXPoster::class . ':shareList',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/share_poster/detail' => [
            'method' => ['get'],
            'call' => WXPoster::class . ':shareDetail',
            'middles' => [AppAuthMiddleWare::class]
        ],
    ];
}
