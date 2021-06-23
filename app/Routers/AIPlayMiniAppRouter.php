<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021/6/23
 * Time: 11:40
 */
namespace App\Routers;

use App\Controllers\AIPlayMiniapp\Auth;
use App\Controllers\AIPlayMiniapp\Opn;
use App\Middleware\AIPlayMiniAppAuthCheckMiddleware;

class AIPlayMiniAppRouter extends RouterBase
{
    protected $logFilename = 'operation_ai_play_miniapp.log';
    public $middleWares = [AIPlayMiniAppAuthCheckMiddleware::class];

    protected $uriConfig = [
        '/ai_play_miniapp/auth/verify_token' => [
            'method'  => ['post'],
            'call'    => Auth::class.':verifyToken',
            'middles' => []
        ],
        '/ai_play_miniapp/opn/lessons' => [
            'method'  => ['get'],
            'call'    => Opn::class.':lessons',
        ],
        '/ai_play_miniapp/opn/lesson' => [
            'method'  => ['get'],
            'call'    => Opn::class.':lesson',
        ],
    ];
}
