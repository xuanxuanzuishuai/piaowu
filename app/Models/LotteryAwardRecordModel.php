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

    /**
     * @param $opActivityId
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public static function getHitAwardByTime($opActivityId, $startTime, $endTime): array
    {
        $db = MysqlDB::getDB();
        $hitList = $db->select(self::getTableNameWithDb() . '(ar)', [
            "[><]" . ErpStudentModel::getTableNameWithDb() . '(s)'        => ['ar.uuid' => 's.uuid'],
            "[><]" . LotteryAwardInfoModel::getTableNameWithDb() . '(ai)' => ['ar.award_id' => 'ai.id'],
        ], [
            's.mobile',
            'ar.award_id',
            'ai.name',
            'ar.create_time'
        ], [
            'ar.op_activity_id' => $opActivityId,
            'ar.award_type[!]'  => Constants::AWARD_TYPE_EMPTY,
            'ar.create_time[>]' => $startTime,
            'ar.create_time[<]' => $endTime,
            'ORDER'             => [
                'ar.id' => 'DESC'
            ]
        ]);
        return $hitList ?: [];
    }

    /**
     * 搜索数据
     * @param $whereParams
     * @param $fields
     * @param $page
     * @param $limit
     * @return array
     */
    public static function search($whereParams, $fields, $page, $limit): array
    {
        $recordsData = [
            'total' => 0,
            'list'  => [],
        ];
        $db = MysqlDB::getDB();
        $joinData = [
            "[><]" . LotteryAwardInfoModel::$table . '(ai)' => ['ar.award_id' => 'id'],
        ];
        $count = $db->count(self::$table . '(ar)', $joinData, ['ar.id'], $whereParams);
        if (empty($count)) {
            return $recordsData;
        }
        $recordsData['total'] = $count;
        $whereParams['ORDER'] = ['ar.id' => 'DESC'];
        $whereParams['LIMIT'] = [($page - 1) * $limit, $limit];
        $recordsData['list'] = $db->select(self::$table . '(ar)', $joinData, $fields, $whereParams);
        return $recordsData;
    }
}
