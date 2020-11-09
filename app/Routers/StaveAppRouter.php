<?php
/**
 * 识谱大作战APP
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/09
 * Time: 14:02
 */

namespace App\Routers;

use App\Controllers\StaveApp\OpernMiniapp;

class StaveAppRouter extends RouterBase
{
    protected $logFilename = 'dss_stave_app.log';

    protected $uriConfig = [
        // 获取当前启用的识谱小程序原始ID
        '/stave_app/opern_miniapp/getid' => [
            'method'  => ['get'],
            'call'    => OpernMiniapp::class . ':getID',
        ],
    ];
}