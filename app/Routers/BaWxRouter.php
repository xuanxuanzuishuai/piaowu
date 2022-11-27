<?php

namespace App\Routers;


use App\Middleware\WeChatAuthCheckMiddleware;
use App\Middleware\WeChatOpenIdCheckMiddleware;
use App\Controllers\BaWx\Wx;
use App\Controllers\BaWx\Receipt;
class BaWxRouter extends RouterBase
{
    protected $logFilename = 'ba_wx.log';
    public $middleWares = [WeChatAuthCheckMiddleware::class];
    protected $uriConfig = [

        /** 公共 */
        '/student_wx/common/js_config' => ['method' => ['get'], 'call'=>'\App\Controllers\StudentWX\Common:getJsConfig', 'middles' => []],
        '/ba_wx/wx/login'    => ['method'=>['get'],'call'=>  Wx::class. ':login', 'middles' => [WeChatOpenIdCheckMiddleware::class]],

        '/ba_wx/wx/shop_list'    => ['method'=>['get'],'call'=>  Wx::class. ':shopList', 'middles' => []],
        '/ba_wx/wx/apply'    => ['method'=>['post'],'call'=>  Wx::class. ':apply', 'middles' => []],
        '/ba_wx/wx/apply_info'    => ['method'=>['get'],'call'=>  Wx::class. ':applyInfo'],
        '/ba_wx/wx/add_receipt'    => ['method'=>['post'],'call'=>  Receipt::class . ':addReceipt'],

    ];
}
