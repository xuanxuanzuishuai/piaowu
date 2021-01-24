<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/21
 * Time: 10:52
 */

namespace App\Models;

class AgentDivideRulesModel extends Model
{
    public static $table = "agent_divide_rules";
    //状态:1正常 2删除
    const STATUS_OK = 1;
    const STATUS_DEL = 2;
    //利润分成类型:1线索周期
    const TYPE_LEADS = 1;

}