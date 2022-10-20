<?php
/**
 * 清晨转介绍路由
 */

namespace App\Routers;

use App\Controllers\Morning\MorningClockActivity;
use App\Middleware\MorningAuthMiddleWare;

class MorningRouter extends RouterBase
{
    protected $logFilename = 'operation_morning.log';
    public    $middleWares = [MorningAuthMiddleWare::class];

    protected $uriConfig = [
        // 5日打卡活动 - 首页
        '/morning/wx/clock_activity/index'      => ['method' => ['get'], 'call' => MorningClockActivity::class . ':getClockActivityIndex'],
        // 5日打卡活动 - 打卡详情页
        '/morning/wx/clock_activity/day_detail'     => ['method' => ['get'], 'call' => MorningClockActivity::class . ':getClockActivityDayDetail'],
        // 5日打卡活动 - 上传截图
        '/morning/wx/clock_activity/activity_upload'     => ['method' => ['post'], 'call' => MorningClockActivity::class . ':clockActivityUpload'],
        // 5日打卡活动 - 海报邀请语页面
        '/morning/wx/clock_activity/activity_share_word' => ['method' => ['get'], 'call' => MorningClockActivity::class . ':getClockActivityShareWord'],

    ];
}