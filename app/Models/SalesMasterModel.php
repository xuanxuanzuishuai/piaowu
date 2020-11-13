<?php

namespace App\Models;

use App\Libs\MysqlDB;

class SalesMasterModel extends Model
{
    /* 通过手机哈将鲸鱼跳跃里面的客户映射到dss的学生*/
    public static $table = 'sales_master_customer';
}