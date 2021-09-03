<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/9/1
 * Time: 15:41
 */

namespace App\Routers;


use App\Controllers\StudentApp\RealActivity;
use App\Middleware\RealStudentAppAuthCheckMiddleware;

/**
 * 真人业务线学生app端接口路由文件
 * Class StudentWXRouter
 * @package App\Routers
 */
class RealStudentAppRouter extends RouterBase
{
    protected $logFilename = 'operation_real_student_app.log';
    public $middleWares = [RealStudentAppAuthCheckMiddleware::class];
    protected $uriConfig = [
        // 月月有奖 && 周周领奖
        '/real_student_app/activity/week' => ['method' => ['post'], 'call' => RealActivity::class . ':getWeekActivity'],
        '/real_student_app/activity/month' => ['method' => ['post'], 'call' => RealActivity::class . ':getMonthActivity'],
        '/real_student_app/activity/can_participate_week' => ['method' => ['post'], 'call' => RealActivity::class . ':getCanParticipateWeekActivityList'],
        '/real_student_wx/activity/week_poster_upload' => ['method' => ['post'], 'call' => RealActivity::class . ':weekActivityPosterScreenShotUpload'],
    ];
}
