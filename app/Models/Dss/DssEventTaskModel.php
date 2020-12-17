<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/27
 * Time: 14:52
 */
namespace App\Models\Dss;

class DssEventTaskModel extends DssModel
{
    const STATUS_NORMAL = 1; // 启用
    const STATUS_DOWN = 2; // 禁用
    public static $table = "erp_event_task";
}