<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/5/21
 * Time: 3:06 PM
 */

namespace App\Libs;


use App\Models\ErpGoodsModel;
use App\Models\ErpGoodsPackageModel;
use App\Models\ErpGoodsPackageRiseModel;
use App\Models\ErpPackageModel;
use App\Models\Model;
use App\Services\Queue\TableSyncTopic;

class TableSyncQueue
{
    const EVENT_TYPE_SYNC = 'sync';

    const TABLE_WHITE_LIST = [
        'erp_package' => ErpPackageModel::class,
        'erp_goods' => ErpGoodsModel::class,
        'erp_goods_package' => ErpGoodsPackageModel::class,
        'erp_goods_package_rise' => ErpGoodsPackageRiseModel::class,
    ];

    /**
     * @param $table
     * @param $id
     * @param $record
     * @throws \Exception
     */
    public static function send($table, $id, $record)
    {
        $topic = new TableSyncTopic();
        $topic->sync($table, $id, $record)->publish();
    }

    public static function receive($message)
    {
        $model = self::getModel($message['table']);
        if (empty($model)) {
            return ;
        }

        $record = $model::getById($message['id']);

        if (empty($record)) {
            $ret = $model::insertRecord($message['record']);
        } else {
            $ret = $model::updateRecord($message['id'], $message['record']);
        }

        if (empty($ret)) {
            SimpleLogger::error('table sync receive error', [
                'message' => $message
            ]);
        }
    }

    /**
     * @param $table
     * @return Model
     */
    public static function getModel($table)
    {
        return self::TABLE_WHITE_LIST[$table] ?? null;
    }
}