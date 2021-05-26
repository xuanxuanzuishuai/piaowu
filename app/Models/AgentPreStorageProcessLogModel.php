<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/5/26
 * Time: 10:52
 */

namespace App\Models;


class AgentPreStorageProcessLogModel extends Model
{
    public static $table = "agent_pre_storage_process_log";
    //日志结果类型:1推广消耗 2年卡预存
    const TYPE_PROMOTION_CONSUMPTION = 1;
    const TYPE_NORMAL_CARD_STORAGE = 2;
}