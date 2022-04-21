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
            "[><]".LotteryAwardInfoModel::$table.'(ai)'=>['ar.award_id'=>'id'],
        ],[
            'ar.uuid',
            'ai.name',
            'ar.create_time'
        ],[
            'ar.op_activity_id' => $opActivityId,
            'ar.award_type[!]'  => Constants::AWARD_TYPE_EMPTY,
            'ar.create_time[>]' => $startTime,
            'ar.create_time[<]' => $endTime,
            'ORDER'          => [
                'ar.id' => 'DESC'
            ],
            'LIMIT'=>10
        ]);
        return $hitList ?: [];
    }

    /**
     * 获取指定用户的中奖记录
     * @param $uuid
     * @param $page
     * @param $pageSize
     * @return array
     */
    public static function getHitRecord($uuid, $page, $pageSize)
    {
        $db = MysqlDB::getDB();
        $join = [
            "[><]" . LotteryAwardInfoModel::$table . '(ai)' => ['ar.award_id' => 'id'],
        ];
        $fields = [
            'ar.id(record_id)',
            'ai.name',
            'ai.level',
            'ai.img_url',
            'ar.create_time',
            'ar.shipping_status',
        ];
        $where = [
            'ar.uuid' => $uuid,
            'ORDER'   => ['ar.id' => 'DESC'],
            'LIMIT'   => [($page - 1) * $pageSize, $pageSize],
        ];
        $count = $db->count(self::$table . '(ar)', $join, $fields, $where);
        if (empty($count)) {
            return [
                'total' => 0,
                'list'  => []
            ];
        }

        $res['total'] = $count;
        $res['list'] = $db->select(self::$table . '(ar)', $join, $fields, $where);
        return $res;
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
