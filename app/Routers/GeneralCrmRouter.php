<?php
/**
 * 3s真人
 * User: qingfeng.lian
 */

namespace App\Routers;

use App\Controllers\API\GeneralCrm;
use App\Middleware\OrgWebMiddleware;
use App\Middleware\SignMiddleware;

class GeneralCrmRouter extends RouterBase
{
    protected $logFilename = 'operation_general_crm.log';
    public    $middleWares = [OrgWebMiddleware::class, SignMiddleware::class];

    protected $uriConfig = [
        // - 获取当前正在运行的活动列表
        '/general_crm/activity/list'                 => ['method' => ['get'], 'call' => GeneralCrm::class . ':getActivityList'],
        // - 每一个活动的奖励规则列表
        '/general_crm/activity/award_rule'           => ['method' => ['get'], 'call' => GeneralCrm::class . ':getActivityAwardRule'],
        // - 学生可参与活动历史记录列表（带筛选条件）
        '/general_crm/student/join_activity_history' => ['method' => ['get'], 'call' => GeneralCrm::class . ':getStudentJoinActivityHistory'],
        // 获取学生可参与活动历史记录列表（活动名称+活动id）
        '/general_crm/student/join_activity_history_list' => ['method' => ['get'], 'call' => GeneralCrm::class . ':getStudentJoinActivityHistoryList'],
        // - 获取学生某个互动参与的详情记录
        '/general_crm/student/activity_join_records' => ['method' => ['get'], 'call' => GeneralCrm::class . ':getStudentActivityJoinRecords'],
    ];
}