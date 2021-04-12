<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/21
 * Time: 10:52
 */

namespace App\Models;


class AgentDispatchLogModel extends Model
{
    public static $table = "agent_dispatch_log";
    const RESULT_TYPE_BILL_NOT_AGENT_MAP_RELATION = 1;//订单没有代理映射关系
    const RESULT_TYPE_REPEAT_BUY_TRAIL = 2;//禁止重复购买体验课
    const RESULT_TYPE_HAVE_BUY_NORMAL_AFTER_STOP_BUY_TRAIL = 3;//已购买正式课，通过代理商再次购买体验课，不做任何处理
}