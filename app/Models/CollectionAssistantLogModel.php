<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/03/22
 * Time: 5:14 PM
 */

namespace App\Models;

class CollectionAssistantLogModel extends Model
{
    //表名称
    public static $table = "collection_assistant_log";
    //操作类型 1调整班级助教
    const OPERATE_TYPE_ALLOT_ASSISTANT = 1;
}