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
        '/dss/red_pack/red_pack_info' => ['method' => ['get'], 'call' => Dss::class . ':redPackInfo'],
        // 海报底图数据
        '/dss/poster_base/info' => ['method' => ['get'], 'call' => Dss::class . ':posterBaseInfo'],
        // 消息信息：
        '/dss/message/info' => ['method' => ['get'], 'call' => Dss::class . ':messageInfo'],
        // 代理和订单映射关系
        '/dss/agent_bill_map/make' => ['method' => ['post'], 'call' => Dss::class . ':makeAgentBillMap'],
        // 第三方订单查询列表
        '/dss/third_part_bill/list' => ['method' => ['get'], 'call' => Dss::class . ':thirdBillList'],
        '/dss/user/logout' => ['method' => ['post'], 'call' => Dss::class . ':tokenLogout'],
        // 检测当前代理商分成模式是否可以进行社群分班
        '/dss/agent/distribution_class_condition' => ['method' => ['get'], 'call' => Dss::class . ':distributionClassCondition'],
        // 海报管理接口
        '/dss/poster/get_path_id' => ['method' => ['post'], 'call' => Dss::class . ':getPathId'],   // 不存在新增
    ];
}