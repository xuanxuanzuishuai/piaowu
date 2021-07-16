<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/6/10
 * Time: 10:52
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;

class CountingActivityMutesModel extends Model
{
    public static $table = "counting_activity_mutex";

    const EFFECTIVE_STATUS = 1; //有效
    const INVALID_STATUS = 2; //无效

}