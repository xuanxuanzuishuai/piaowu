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

class StudentCollectionLogModel extends Model
{
    public static $table = 'student_collection_log';
    public static $redisExpire = 1;

    //操作类型 分配助教
    const OPERATE_TYPE_ALLOT = 1;

}