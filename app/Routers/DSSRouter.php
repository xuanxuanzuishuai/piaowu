<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/24
 * Time: 17:15
 */

namespace App\Routers;

use App\Controllers\OrgWeb\EmployeeActivity;
use App\Controllers\Referral\Invite;

class DSSRouter extends RouterBase
{
    protected $logFilename = 'operation_dss.log';
    protected $uriConfig = [

        '/dss/employee_activity/active_list' => ['method' => ['get'], 'call' => EmployeeActivity::class . ':activeList'],
        '/dss/employee_activity/get_poster'  => ['method' => ['get'], 'call' => EmployeeActivity::class . ':getPoster'],

        '/dss/referral/list' => ['method' => ['get'], 'call' => Invite::class . ':list'],
        '/dss/referral/referral_info' => ['method' => ['get'], 'call' => Invite::class . ':referralDetail'],
    ];
}