<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/16
 * Time: 3:52 PM
 */

namespace App\Routers;

use App\Controllers\StudentApp\ActivityCenter;
use App\Controllers\StudentApp\App;
use App\Controllers\StudentApp\Auth;
use App\Controllers\StudentApp\DuanWuActivity;
use App\Controllers\StudentApp\GoldLeafShop;
use App\Controllers\StudentApp\Order;
use App\Controllers\StudentApp\Poster;
use App\Controllers\StudentWX\Activity;
use App\Controllers\StudentWX\Poster AS WXPoster;
use App\Controllers\StudentApp\ReferralActivity;
use App\Controllers\StudentWX\Student;
use App\Controllers\StudentWX\Task;
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

        // 月月有奖 && 周周领奖
        // 海报列表
        '/student_app/poster/can_join' => [
            'method' => ['get'],
            'call' => Poster::class . ':canJoin',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/poster/list' => [
            'method' => ['get'],
            'call' => Poster::class . ':list',
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

        //任务中心
        '/student_app/task/list' => [
            'method' => ['get'],
            'call' => Task::class . ':list',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/task/award_record' => [
            'method' => ['get'],
            'call' => Task::class . ':awardRecord',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/task/get_award_details' => [
            'method' => ['get'],
            'call' => Task::class . ':getAwardDetails',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/task/get_express_info' => [
            'method' => ['get'],
            'call' => Task::class . ':getExpressInfo',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/task/get_goods_info' => [
            'method' => ['get'],
            'call' => Task::class . ':getGoodsInfo',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/task/sign_up' => [
            'method' => ['post'],
            'call' => Task::class . ':signUp',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/task/get_rewards' => [
            'method' => ['post'],
            'call' => Task::class . ':getRewards',
            'middles' => [AppAuthMiddleWare::class]
        ],

        '/student_app/student/address_list' => [
            'method' => ['get'],
            'call' => \App\Controllers\StudentWeb\Student::class . ':addressList',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/student/modify_address' => [
            'method' => ['post'],
            'call' => \App\Controllers\StudentWeb\Student::class . ':modifyAddress',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/area/get_by_parent_code' => [
            'method' => ['get'],
            'call' => \App\Controllers\StudentWeb\Area::class . ':getByParentCode',
            'middles' => [AppAuthMiddleWare::class]
        ],

        /* 五日打卡返学费相关 */
        '/student_app/sign/upload'            => [
            'method'  => ['post'],
            'call'    => \App\Controllers\StudentApp\Activity::class . ':signInUpload',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/sign/data'              => [
            'method'  => ['get'],
            'call'    => \App\Controllers\StudentApp\Activity::class . ':signInData',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/sign/copy_writing'      => [
            'method'  => ['get'],
            'call'    => \App\Controllers\StudentApp\Activity::class . ':signInCopyWriting',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/student/account_detail' => [
            'method'  => ['get'],
            'call'    => Student::class . ':accountDetail',
            'middles' => [AppAuthMiddleWare::class]
        ],

        /* 跑马灯数据 */
        '/student_app/activity/user_reward_details' => [
            'method'  => ['get'],
            'call'    => \App\Controllers\StudentApp\Activity::class . ':userRewardDetails',
            'middles' => [AppAuthMiddleWare::class]
        ],

        //获取小程序码
        '/student_app/poster/get_qr_path' => [
            'method' => ['get'],
            'call' => Poster::class . ':getQrPath',
            'middles' => [AppAuthMiddleWare::class],
        ],

        //活动中心
        '/student_app/activity_center/list' => [
            'method'  => ['get'],
            'call'    => ActivityCenter::class . ':getList',
            'middles' => [AppAuthMiddleWare::class],
        ],

        /* 虚拟拼团相关 */
        '/student_app/activity/collage_index' => [
            'method'  => ['get'],
            'call'    => \App\Controllers\StudentApp\Activity::class . ':collageIndex',
            'middles' => [],
        ],
        '/student_app/activity/collage_detail' => [
            'method'  => ['get'],
            'call'    => \App\Controllers\StudentApp\Activity::class . ':collageDetail',
            'middles' => [AppAuthMiddleWare::class],
        ],
        '/student_app/activity/assistant_info' => [
            'method'  => ['get'],
            'call'    => \App\Controllers\StudentApp\Activity::class . ':assistantInfo',
            'middles' => [AppAuthMiddleWare::class],
        ],
        '/student_app/order/create' => [
            'method'  => ['post'],
            'call'    => Order::class . ':createBill',
            'middles' => [AppAuthMiddleWare::class],
        ],
        '/student_app/order/status' => [
            'method'  => ['get'],
            'call'    => Order::class . ':billStatus',
            'middles' => [AppAuthMiddleWare::class],
        ],
        '/student_app/points_shop/rule_desc' => [
            'method' => ['get'],
            'call' => GoldLeafShop::class . ':ruleDesc',
            'middles' => []
        ],
    ];
}
