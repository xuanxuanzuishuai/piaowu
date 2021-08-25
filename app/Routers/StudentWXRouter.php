<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/21
 * Time: 2:55 PM
 */

namespace App\Routers;


use App\Controllers\StudentWX\RtActivity;
use App\Controllers\StudentWX\DuanWuActivity;
use App\Controllers\StudentWX\GoldLeafShop;
use App\Controllers\StudentWX\Poster;
use App\Controllers\StudentWX\Task;
use App\Middleware\WeChatAuthCheckMiddleware;
use App\Middleware\WeChatOpenIdCheckMiddleware;
use App\Controllers\StudentWX\Student;
use App\Controllers\StudentWX\Activity;

class StudentWXRouter extends RouterBase
{
    protected $logFilename = 'operation_student_wx.log';
    public $middleWares = [WeChatAuthCheckMiddleware::class];
    protected $uriConfig = [

        /** 公共 */
        '/student_wx/common/js_config' => ['method' => ['get'], 'call'=>'\App\Controllers\StudentWX\Common:getJsConfig', 'middles' => []],
        '/student_wx/student/getOtherToken' => ['method' => ['get'], 'call' => Student::class . ':getOtherToken'],

        /** 用户信息 */
        '/student_wx/student/account_detail' => ['method' => ['get'], 'call' => Student::class . ':accountDetail'],
        '/student_wx/student/register' => ['method'=>['post'],'call' => Student::class . ':register', 'middles' => []],
        '/student_wx/student/login'    => ['method'=>['get'],'call'=>Student::class . ':login', 'middles' => [WeChatOpenIdCheckMiddleware::class]],
        '/student_wx/student/send_sms_code' => ['method'=>['get'],'call'=>Student::class.':sendSmsCode', 'middles' => []],

        // 获取分享海报：
        '/student_wx/employee_activity/poster' => ['method' => ['get'], 'call' => Student::class . ':getPosterList'],
        //每日打卡活动相关路由
        '/student_wx/sign/upload' => ['method' => ['post'], 'call' => Activity::class . ':signInUpload'],
        '/student_wx/sign/data' => ['method' => ['get'], 'call' => Activity::class . ':signInData'],
        '/student_wx/sign/copy_writing' => ['method' => ['get'], 'call' => Activity::class . ':signInCopyWriting'],
        '/student_wx/menu/test' => ['method' => ['get', 'post'], 'call' => Student::class . ':menuTest', 'middles' => []],
        '/student_wx/menu/redirect' => ['method' => ['get', 'post'], 'call' => Student::class . ':menuRedirect', 'middles' => []],

        /** 积分商城 start */
        '/student_wx/points_shop/gold_leaf_list' => ['method' => ['get'], 'call' => GoldLeafShop::class . ':goldLeafList'],  // 获取待发放金叶子积分明细

        // 月月有奖 && 周周领奖
        // 海报列表
        '/student_wx/poster/list'          => ['method' => ['get'], 'call' => Poster::class . ':list'],
        '/student_wx/poster/upload'        => ['method' => ['post'], 'call' => Poster::class . ':upload'],
        '/student_wx/poster/get_qr_path'   => ['method' => ['get'], 'call' => Poster::class . ':getQrPath'],
        '/student_wx/text/list'            => ['method' => ['get'], 'call' => Poster::class . ':wordList'],
        '/student_wx/activity/list'        => ['method' => ['get'], 'call' => Activity::class . ':weekActivityList'],
        '/student_wx/share_poster/list'    => ['method' => ['get'], 'call' => Poster::class . ':shareList'],
        '/student_wx/share_poster/detail'  => ['method' => ['get'], 'call' => Poster::class . ':shareDetail'],
        '/student_wx/referral/invite_list' => ['method' => ['get'], 'call' => Student::class . ':inviteList'],


        //端午节活动
        '/student_wx/duanwu_activity/activity_info' => ['method' => ['get'], 'call' => DuanWuActivity::class . ':activityInfo'],
        '/student_wx/duanwu_activity/referee_list' => ['method' => ['get'], 'call' => DuanWuActivity::class . ':refereeList'],

        // 弹幕优化：
        '/student_wx/landing/broadcast' => ['method' => ['get'], 'call' => Student::class . ':broadcast', 'middles' => []],

        //RT优惠券
        '/student_wx/rt_activity/invite_index' => ['method' => ['post'], 'call'   => RtActivity::class . ':inviteIndex','middles' => []],
        '/student_wx/rt_activity/invited_index' => ['method' => ['post'], 'call'   => RtActivity::class . ':invitedIndex','middles' => []],
        '/student_wx/rt_activity/get_poster' => ['method' => ['post'], 'call'   => RtActivity::class . ':getPoster'],
        '/student_wx/rt_activity/receive_coupon' => ['method' => ['post'], 'call'   => RtActivity::class . ':receiveCoupon'],
        '/student_wx/rt_activity/coupon_collect' => ['method' => ['post'], 'call'   => RtActivity::class . ':couponCollect'],
        '/student_wx/rt_activity/get_invite_record' => ['method' => ['post'], 'call'   => RtActivity::class . ':getInviteRecord'],


        //任务中心
        '/student_wx/task/list' => ['method' => ['get'], 'call' => Task::class . ':list'],
        '/student_wx/task/award_record' => ['method' => ['get'], 'call' => Task::class . ':awardRecord'],
        '/student_wx/task/get_award_details' => ['method' => ['get'], 'call' => Task::class . ':getAwardDetails'],
        '/student_wx/task/get_express_info' => ['method' => ['get'], 'call' => Task::class . ':getExpressInfo'],
        '/student_wx/task/get_goods_info' => ['method' => ['get'], 'call' => Task::class . ':getGoodsInfo'],
        '/student_wx/task/sign_up' => ['method' => ['post'], 'call' => Task::class . ':signUp'],
        '/student_wx/task/get_rewards' => ['method' => ['post'], 'call' => Task::class . ':getRewards'],

        /* 跑马灯数据 */
        '/student_wx/activity/user_reward_details' => ['method' => ['get'], 'call' => Activity::class . ':userRewardDetails'],
    ];
}
