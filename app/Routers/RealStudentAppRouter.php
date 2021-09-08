<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/9/1
 * Time: 15:41
 */

namespace App\Routers;


use App\Controllers\Real\StudentActivity;
use App\Middleware\RealStudentAppAndWxAuthCheckMiddleware;

/**
 * 真人业务线学生app端接口路由文件
 * Class RealStudentAppRouter
 * @package App\Routers
 */
class RealStudentAppRouter extends RouterBase
{
    protected $logFilename = 'operation_real_student_app.log';
    public $middleWares = [RealStudentAppAndWxAuthCheckMiddleware::class];
    protected $uriConfig = [
        // 月月有奖 && 周周领奖
        '/real_student_app/activity/week' => ['method' => ['post'], 'call' => StudentActivity::class . ':getWeekActivity'],
        '/real_student_app/activity/month' => ['method' => ['post'], 'call' => StudentActivity::class . ':getMonthActivity'],
        '/real_student_app/activity/can_participate_week' => ['method' => ['post'], 'call' => StudentActivity::class . ':getCanParticipateWeekActivityList'],
        '/real_student_wx/activity/week_poster_upload' => ['method' => ['post'], 'call' => StudentActivity::class . ':weekActivityPosterScreenShotUpload'],
        '/real_student_app/activity/share_poster_history' => ['method' => ['post'], 'call' => StudentActivity::class . ':sharePosterHistory'],
        '/real_student_app/activity/share_poster_detail' => ['method' => ['post'], 'call' => StudentActivity::class . ':sharePosterDetail'],

        // 周周领奖分享海报文案列表
        '/real_student_app/poster/word_list' => ['method' => ['post'], 'call' => StudentActivity::class . ':realSharePosterWordList'],
        // 月月有奖二次分享海报对应的二维码
        '/real_student_app/poster/get_qr_path'   => ['method' => ['post'], 'call' => StudentActivity::class . ':getQrPath'],
    ];
}
