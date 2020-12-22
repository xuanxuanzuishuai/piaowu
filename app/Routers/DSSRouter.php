<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/24
 * Time: 17:15
 */

namespace App\Routers;

use App\Controllers\API\Dss;
use App\Controllers\Referral\Invite;

class DSSRouter extends RouterBase
{
    protected $logFilename = 'operation_dss.log';
    protected $uriConfig = [

        '/dss/employee_activity/active_list' => ['method' => ['get'], 'call' => Dss::class . ':activeList'],
        '/dss/employee_activity/get_poster'  => ['method' => ['get'], 'call' => Dss::class . ':getPoster'],

        '/dss/referral/list' => ['method' => ['get'], 'call' => Invite::class . ':list'],
        '/dss/referral/referral_info' => ['method' => ['get'], 'call' => Invite::class . ':referralDetail'],
        '/dss/referral/referee_all_user' => ['method' => ['get'], 'call' => Invite::class . ':refereeAllUser'],

        '/dss/share_post/get_params_id' => ['method' => ['post'], 'call' => Dss::class . ':getParamsId'],
        '/dss/share_post/get_params_info' => ['method' => ['get'], 'call' => Dss::class . ':getParamsInfo'],
        '/dss/referral/create_relation' => ['method' => ['post'], 'call' => Dss::class . ':createRelation'],
        '/dss/red_pack/red_pack_info' => ['method' => ['get'], 'call' => Dss::class . ':redPackInfo']
    ];
}