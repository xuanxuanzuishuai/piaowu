<?php
/**
 * 周周领奖活动用户命中ab测海报信息
 * User: qingfeng.lian
 * Date: 2022/04/02
 * Time: 18:52
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssGiftCodeModel;

class RealWeekActivityUserAllocationABModel extends Model
{
    public static $table = "real_week_activity_user_allocation_ab";

}