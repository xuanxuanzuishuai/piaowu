<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/8/16
 * Time: 2:56 PM
 */

namespace App\Routers;

use App\Controllers\Admin\Menu;
use App\Controllers\API\OceanEngine;
use App\Middleware\AdminMiddleware;

class APIRouter extends RouterBase
{
    protected $logFilename = 'dss_api.log';

    protected $uriConfig = [
        '/api/ocean_engine/track' => [
            'method' => ['get'],
            'call' => OceanEngine::class . ':track',
            'middles' => [],
        ],
    ];
}