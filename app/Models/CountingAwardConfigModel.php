<?php
/**
 * 计数任务奖品配置
 *
 * User: xingkuiYu
 * Date: 2021/7/13
 * Time: 10:35 AM
 */

namespace App\Models;


use App\Libs\SimpleLogger;

class CountingAwardConfigModel extends Model
{
    public static $table = "counting_award_config";

    //状态
    const INVALID_STATUS = 2; //无效
    const EFFECTIVE_STATUS = 1; //有效
    //类型
    const GOLD_LEAF_TYPE = 1; //类型 金叶子
    const PRODUCT_TYPE = 2; //类型 实物

}
