<?php

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Models\Erp\ErpStudentModel;

class LotteryAwardRecordModel extends Model
{
    public static $table = 'lottery_award_record';

    const USE_TYPE_FILTER = 1;
    const USE_TYPE_IMPORT = 2;

    public static function getHitAwardByTime($opActivityId, $startTime, $endTime)
    {
        $db  = MysqlDB::getDB();
        $hitList = $db->select(self::$table.'(ar)',[
            "[><]".ErpStudentModel::$table.'(s)'=>['ar.uuid'=>'s.uuid'],
            "[><]".LotteryAwardInfoModel::$table.'(ai)'=>['ar.award_id'=>'ai.id'],
        ],[
            's.mobile',
            'ar.award_id',
            'ai.name',
            'ar.create_time'
        ],[
            'ar.op_activity_id' => $opActivityId,
            'ar.award_type[!]'  => Constants::AWARD_TYPE_EMPTY,
            'ar.create_time[>]' => $startTime,
            'ar.create_time[<]' => $endTime,
            'ORDER'          => [
                'ar.id' => 'DESC'
            ]
        ]);
        return $hitList ?: [];
    }
}
