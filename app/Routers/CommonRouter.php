<?php

namespace App\Routers;

use App\Controllers\Common\Area;

class CommonRouter extends RouterBase
{
    protected $logFilename = 'common.log';
    public    $middleWares = [];
    protected $uriConfig = [
        '/common/area/province' => ['method' => ['get'], 'call' => Area::class . ':provinceList'],
        '/common/area/city' => ['method' => ['get'], 'call' => Area::class . ':cityList'],
        '/common/area/district' => ['method' => ['get'], 'call' => Area::class . ':districtList'],
    ];
}