<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/8/16
 * Time: 2:56 PM
 */

namespace App\Routers;

use App\Controllers\Admin\Menu;
use App\Controllers\API\Track;
use App\Middleware\AdminMiddleware;

class APIRouter extends RouterBase
{
    protected $logFilename = 'dss_api.log';

    protected $uriConfig = [
        '/api/track/ad_event/ocean_engine' => [
            'method' => ['get'],
            'call' => Track::class . ':adEventOceanEngine',
            'middles' => [],
        ],
        '/api/track/ad_event/gdt' => [
            'method' => ['get'],
            'call' => Track::class . ':adEventGdt',
            'middles' => [],
        ],
    ];
}