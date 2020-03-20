<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/5
 * Time: 8:17 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\Util;

class StudentAssistantLogModel extends Model
{
    public static $table = 'student_assistant_log';
    public static $redisExpire = 1;

    //操作类型 1分配助教 2班级分配助教触发的学生分配助教
    const OPERATE_TYPE_ALLOT = 1;
    const OPERATE_TYPE_ALLOT_COLLECTION_ASSISTANT = 2;

}