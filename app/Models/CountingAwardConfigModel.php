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

class CountingAwardConfigModel extends Model
{
    public static $table = "counting_award_config";

    //状态
    const EFFECTIVE_STATUS = 1;
    const INVALID_STATUS = 2;

    //类型
    const LEAF_TYPE    = 1;
    const PRODUCT_TYPE = 2;


}