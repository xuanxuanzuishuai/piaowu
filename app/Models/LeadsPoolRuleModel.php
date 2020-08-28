<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/08/30
 * Time: 1:14 PM
 */

namespace App\Models;

class LeadsPoolRuleModel extends Model
{
    //表名称
    public static $table = "leads_pool_rule";
    //状态：1启用 2禁用 3删除
    const LEADS_POOL_RULE_STATUS_ABLE = 1;
    const LEADS_POOL_RULE_STATUS_DISABLE = 2;
    const LEADS_POOL_RULE_STATUS_DEL = 3;
}