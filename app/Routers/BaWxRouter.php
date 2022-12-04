<?php

namespace App\Routers;


use App\Middleware\WeChatAuthCheckMiddleware;
use App\Middleware\WeChatOpenIdCheckMiddleware;
use App\Controllers\BaWx\Wx;
use App\Controllers\BaWx\AWARD;
use App\Controllers\BaWx\Receipt;
class BaWxRouter extends RouterBase
{
    protected $logFilename = 'ba_wx.log';
    public $middleWares = [WeChatAuthCheckMiddleware::class];
    protected $uriConfig = [

        /** 公共 */
        '/ba_wx/wx/js_config' => ['method' => ['get'], 'call'=> Wx::class. ':getJsConfig', 'middles' => []],
        '/ba_wx/wx/login'    => ['method'=>['get'],'call'=>  Wx::class. ':login', 'middles' => [WeChatOpenIdCheckMiddleware::class]],

        '/ba_wx/wx/shop_list'    => ['method'=>['get'],'call'=>  Wx::class. ':shopList', 'middles' => []],
        '/ba_wx/wx/apply'    => ['method'=>['post'],'call'=>  Wx::class. ':apply', 'middles' => []],
        '/ba_wx/wx/apply_info'    => ['method'=>['get'],'call'=>  Wx::class. ':applyInfo'],
        '/ba_wx/wx/add_receipt'    => ['method'=>['post'],'call'=>  Receipt::class . ':addReceipt'],

        '/ba_wx/wx/receipt_list'    => ['method'=>['get'],'call'=>  Receipt::class . ':receiptList'],

        '/ba_wx/wx/receipt_info'    => ['method'=>['get'],'call'=>  Receipt::class . ':receiptInfo'],

        '/ba_wx/wx/award_list'    => ['method'=>['get'],'call'=>  Award::class . ':awardList']

    ];
}
