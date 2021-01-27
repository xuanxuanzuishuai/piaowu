<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/21
 * Time: 10:52
 */

namespace App\Models;


class AgentAwardDetailModel extends Model
{
    public static $table = "agent_award_detail";
    //状态：状态：1等待审核 2发放成功 3发放失败 4发放中 5终止发放
    const STATUS_WAIT = 1;
    const STATUS_SUCCESS = 2;
    const STATUS_FAIL = 3;
    const STATUS_ING = 4;
    const STATUS_STOP = 5;
    //奖励动作类型：1购买体验卡 2购买年卡 3注册
    const AWARD_ACTION_TYPE_BUY_TRAIL_CLASS = 1;
    const AWARD_ACTION_TYPE_BUY_FORMAL_CLASS = 2;
    const AWARD_ACTION_TYPE_REGISTER = 3;
}