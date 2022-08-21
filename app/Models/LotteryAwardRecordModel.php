<?php

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use Medoo\Medoo;

class LotteryAwardRecordModel extends Model
{
    public static $table = 'lottery_award_record';

    const USE_TYPE_FILTER = 1;
    const USE_TYPE_IMPORT = 2;
	//抽奖活动修改地址加锁
    const LOTTERY_ENTITY_AWARD_PUSH_ERP_LOCK='lottery_entity_award_push_erp_lock';
    /**
     * 获取未中空奖人数
     * @param $opActivityId
     * @return mixed
     */
    public static function getHitNumNotEmpty($opActivityId)
    {
        $db = MysqlDB::getDB();
        $res = $db->get(self::$table, ['num' => Medoo::raw('count(distinct uuid)')], [
            'op_activity_id'   => $opActivityId,
            'award_type[!]' => Constants::AWARD_TYPE_EMPTY,
        ]);
        return $res['num'] ?? 0;
    }

    /**
     * 获取中奖记录
     * @param $opActivityId
     * @return array
     */
    public static function getHitAwardByTime($opActivityId)
    {
        $db = MysqlDB::getDB();
        $hitList = $db->select(self::$table . '(ar)', [
            "[><]" . LotteryAwardInfoModel::$table . '(ai)' => ['ar.award_id' => 'id'],
        ], [
            'ar.uuid',
            'ai.name',
            'ar.create_time'
        ], [
            'ar.op_activity_id' => $opActivityId,
            'ar.award_type[!]'  => Constants::AWARD_TYPE_EMPTY,
            'ORDER'             => [
                'ar.id' => 'DESC'
            ],
            'LIMIT'             => 15
        ]);
        return $hitList ?: [];
    }

    /**
     * 获取指定用户的中奖记录
     * @param $opActivityId
     * @param $uuid
     * @param $page
     * @param $pageSize
     * @return array
     */
    public static function getHitRecord($opActivityId, $uuid, $page, $pageSize)
    {
        $db = MysqlDB::getDB();
        $join = [
            "[><]" . LotteryAwardInfoModel::$table . '(ai)' => ['ar.award_id' => 'id'],
        ];
        $fields = [
            'ar.id(record_id)',
            'ai.name',
            'ai.type',
            'ai.level',
            'ai.img_url',
            'ar.create_time',
            'ar.shipping_status',
            'ar.express_number(logistics_no)',
        ];
        $where = [
            'ar.uuid' => $uuid,
            'ar.op_activity_id' => $opActivityId,
            'ar.award_type[!]' => Constants::AWARD_TYPE_EMPTY,
        ];
        $count = $db->count(self::$table . '(ar)', $join,['ar.id'], $where);
        if (empty($count)) {
            return [
                'total' => 0,
                'list'  => []
            ];
        }

        $res['total'] = $count;
        $where['ORDER'] = ['ar.id' => 'DESC'];
        $where['LIMIT'] = [($page - 1) * $pageSize, $pageSize];
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
        //limit=0查询全部
        if ($limit != 0) {
            $whereParams['LIMIT'] = [($page - 1) * $limit, $limit];
        }
        $recordsData['list'] = $db->select(self::$table . '(ar)', $joinData, $fields, $whereParams);
        return $recordsData;
    }

    /**
     * 获取未发货的奖励记录
     * @param $shippingStatus
     * @param $awardType
     * @return array
     */
    public static function getUnshippedAwardRecord($shippingStatus, $awardType): array
    {
        $db = MysqlDB::getDB();
        return $db->select(self::$table,
            [
                "[>]" . LotteryAwardInfoModel::$table => ["award_id" => "id"],
                "[>]" . LotteryActivityModel::$table  => ["op_activity_id" => "op_activity_id"],
            ],
            [
                self::$table . ".unique_id",
                self::$table . ".uuid",
                self::$table . ".id",
                self::$table . ".erp_address_id",
                self::$table . ".award_type",
                self::$table . ".activity_version",
                self::$table . ".op_activity_id",
                LotteryAwardInfoModel::$table . ".award_detail",
                LotteryActivityModel::$table . ".app_id",
            ],
            [
                self::$table . ".draw_time[>]"    => 0,
                self::$table . ".shipping_status" => $shippingStatus,
                self::$table . ".award_type"      => $awardType,
                self::$table . ".grant_state"      => Constants::STATUS_FALSE,
            ]);
    }
}
