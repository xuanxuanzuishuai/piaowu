<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/9/1
 * Time: 15:41
 */

namespace App\Routers;


use App\Controllers\Real\MagicStoneShop;
use App\Controllers\Real\StudentActivity;
use App\Middleware\RealStudentAppAndWxAuthCheckMiddleware;

/**
 * 真人业务线学生微信端接口路由文件
 * Class RealStudentWXRouter
 * @package App\Routers
 */
class RealStudentWXRouter extends RouterBase
{
    protected $logFilename = 'operation_real_student_wx.log';
    public $middleWares = [RealStudentAppAndWxAuthCheckMiddleware::class];
    protected $uriConfig = [
        // 月月有奖 && 周周领奖
        '/real_student_wx/activity/week' => ['method' => ['post'], 'call' => StudentActivity::class . ':getWeekActivity'],
        '/real_student_wx/activity/show_tab' => ['method' => ['post'], 'call' => StudentActivity::class . ':monthAndWeekActivityShowTab'],
        '/real_student_wx/activity/month' => ['method' => ['post'], 'call' => StudentActivity::class . ':getMonthActivity'],
        '/real_student_wx/activity/can_participate_week' => ['method' => ['post'], 'call' => StudentActivity::class . ':getCanParticipateWeekActivityList'],
        '/real_student_wx/activity/week_poster_upload' => ['method' => ['post'], 'call' => StudentActivity::class . ':weekActivityPosterScreenShotUpload'],
        '/real_student_wx/activity/share_poster_history' => ['method' => ['post'], 'call' => StudentActivity::class . ':sharePosterHistory'],
        '/real_student_wx/activity/share_poster_detail' => ['method' => ['post'], 'call' => StudentActivity::class . ':sharePosterDetail'],
        // 月月有奖 && 周周领奖 分享海报文案列表
        '/real_student_wx/poster/word_list'   => ['method' => ['post'], 'call' => StudentActivity::class . ':realSharePosterWordList'],
        // 月月有奖二次分享海报对应的二维码
        '/real_student_wx/poster/get_qr_path'   => ['method' => ['post'], 'call' => StudentActivity::class . ':getQrPath'],
        // 月月有奖二次分享跑马灯数据
        '/real_student_wx/activity/award_top_list'   => ['method' => ['post'], 'call' => StudentActivity::class . ':realUserRewardTopList'],
        //魔法石商城规则说明文案
        '/real_student_wx/magic_stone_shop/rule_desc' => ['method' => ['get'], 'call' => MagicStoneShop::class . ':ruleDesc', 'middles' => []],
    ];
}
