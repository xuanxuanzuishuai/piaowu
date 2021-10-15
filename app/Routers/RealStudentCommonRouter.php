<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/10/15
 * Time: 16:47
 */

namespace App\Routers;

use App\Controllers\Real\Common;

class RealStudentCommonRouter extends RouterBase
{
    protected $logFilename = 'operation_real_student_common.log';
    public $middleWares = [];
    protected $uriConfig = [
        //国家区号列表
        '/real_student_common/country_code/list' => ['method' => ['get'], 'call' => Common::class . ':getCountryCode', 'middles' => []],
    ];
}