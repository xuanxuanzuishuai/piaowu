<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/08/30
 * Time: 1:14 PM
 */

namespace App\Models;


class LeadsPoolOpLogModel extends Model
{
    //表名称
    public static $table = "leads_pool_op_log";
    /**
     * 操作类型:1线索池增加 2线索池编辑 3线索池删除 4线索池分配规则增加 5线索池分配规则编辑 6线索池分配规则删除
     */
    const OP_TYPE_POOL_ADD = 1;
    const OP_TYPE_POOL_UPDATE = 2;
    const OP_TYPE_POOL_DEL = 3;
    const OP_TYPE_POOL_RULE_ADD = 4;
    const OP_TYPE_POOL_RULE_UPDATE = 5;
    const OP_TYPE_POOL_RULE_DEL = 6;
}