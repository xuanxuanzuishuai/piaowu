<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/5/26
 * Time: 10:52
 */

namespace App\Models;


class AgentPreStorageDetailModel extends Model
{
    public static $table = "agent_pre_storage_detail";
    //状态:1未消耗 2已消耗
    const STATUS_NOT_CONSUMED = 1;
    const STATUS_CONSUMED = 2;
}