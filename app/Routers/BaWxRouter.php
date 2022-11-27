<?php

namespace App\Routers;


use App\Middleware\WeChatAuthCheckMiddleware;
use App\Middleware\WeChatOpenIdCheckMiddleware;
use App\Controllers\BaWx\Wx;

class BaWxRouter extends RouterBase
{
    protected $logFilename = 'ba_wx.log';
    public $middleWares = [WeChatAuthCheckMiddleware::class];
    protected $uriConfig = [

        /** 公共 */
        '/student_wx/common/js_config' => ['method' => ['get'], 'call'=>'\App\Controllers\StudentWX\Common:getJsConfig', 'middles' => []],
        '/ba_wx/wx/login'    => ['method'=>['get'],'call'=>  Wx::class. ':login', 'middles' => [WeChatOpenIdCheckMiddleware::class]],

    ];
}
