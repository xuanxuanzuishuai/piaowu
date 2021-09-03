<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/9/1
 * Time: 15:41
 */

namespace App\Routers;


use App\Controllers\StudentWX\RealActivity;
use App\Middleware\RealStudentWeChatAuthCheckMiddleware;

/**
 * 真人业务线学生微信端接口路由文件
 * Class StudentWXRouter
 * @package App\Routers
 */
class RealStudentWXRouter extends RouterBase
{
    protected $logFilename = 'operation_real_student_wx.log';
    public $middleWares = [RealStudentWeChatAuthCheckMiddleware::class];
    protected $uriConfig = [
        // 月月有奖 && 周周领奖
        '/real_student_wx/activity/week' => ['method' => ['post'], 'call' => RealActivity::class . ':getWeekActivity'],
        '/real_student_wx/activity/month' => ['method' => ['post'], 'call' => RealActivity::class . ':getMonthActivity'],
        '/real_student_wx/activity/can_participate_week' => ['method' => ['post'], 'call' => RealActivity::class . ':getCanParticipateWeekActivityList'],
        '/real_student_wx/activity/week_poster_upload' => ['method' => ['post'], 'call' => RealActivity::class . ':weekActivityPosterScreenShotUpload'],
    ];
}
