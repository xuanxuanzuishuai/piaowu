<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/10/19
 * Time: 6:14 PM
 */

namespace App\Models\AdTrack;


use App\Libs\Constants;
use App\Libs\MysqlDB;

class PurchaseLog extends AdModel
{
    public static $table = "purchase_log";

    /**
     * 根据UUID获取体验卡信息
     * @param $uuid
     * @return mixed
     */
    public static function getTrailInfoByUuid($uuid)
    {
        $db = MysqlDB::getDB(MysqlDB::CONFIG_AD);
        $purchaseLog = self::$table;
        $webOrderLog = WebOrderLog::$table;
        return $db->get("$purchaseLog(p)", [
            "[><]$webOrderLog(w)" => ['order_id', "order_id"],
        ], [
            'p.id',
            'p.order_id',
            'w.ref'
        ], [
            'p.app_id'     => Constants::QC_APP_ID,
            'p.uuid'       => $uuid,
            'p.order_type' => 1,
        ]);
    }
}