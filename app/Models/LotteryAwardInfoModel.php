<?php

namespace App\Models;

class LotteryAwardInfoModel extends Model
{
    public static $table = 'lottery_award_info';
    // 中奖时间类型:1同活动时间 2自定义
    const HIT_TIME_TYPE_ACTIVITY_TIME = 1;
    const HIT_TIME_TYPE_SELF = 2;
}
