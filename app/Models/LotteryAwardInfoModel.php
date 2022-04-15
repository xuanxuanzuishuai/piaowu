<?php

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Models\Erp\ErpStudentModel;

class LotteryAwardInfoModel extends Model
{
    public static $table = 'lottery_award_info';
    // 中奖时间类型:1同活动时间 2自定义
    const HIT_TIME_TYPE_ACTIVITY_TIME = 1;
    const HIT_TIME_TYPE_SELF = 2;

    public static function getAwardInfoWithRule($opActivityId)
    {
        $db = MysqlDB::getDB();
        return $db->select(self::$table.'(ai)',
           [
                'ai.id',
                'ai.type',
                'ai.level',
                'ai.name',
                'ai.award_detail',
                'ai.img_url',
                'ai.weight',
                'ai.rest_num',
                'ai.hit_times',
            ],[
                'ai.status'=>Constants::STATUS_TRUE,
                'ar.status'=>Constants::STATUS_TRUE,
            ]
        );
    }
}
