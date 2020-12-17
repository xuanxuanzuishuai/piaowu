<?php
namespace App\Models\Erp;

class ErpUserEventTaskModel extends ErpModel
{
    // 完成任务：
    const EVENT_TASK_STATUS_COMPLETE = 2;
    public static $table = 'erp_user_event_task';
}