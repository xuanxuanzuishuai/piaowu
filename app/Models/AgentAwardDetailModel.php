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
    //状态：状态：1等待审核 2发放成功 3发放失败 4发放中:等待第三方支付中心回调 5终止发放
    const STATUS_WAIT = 1;
    const STATUS_SUCCESS = 2;
    const STATUS_FAIL = 3;
    const STATUS_ING = 4;
    const STATUS_STOP = 5;
}