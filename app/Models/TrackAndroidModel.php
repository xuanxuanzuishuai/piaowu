<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/10/19
 * Time: 6:14 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Services\RealAd;
use App\Services\TrackService;

class TrackAndroidModel extends AdModel
{
    public static $table = "real_track_android";

    public static function matchAndroidInfo($where)
    {
        $trackAndroid = self::$table;
        $trackUser    = TrackUserModel::$table;

        $whereNew['u.platform_type'] = RealAd::PLAT_ID_ANDROID;
        foreach ($where as $key => $value) {
            foreach ($value as $k => $v) {
                $whereNew[$key]['a.' . $k] = $v;
            }
        }

        $db = MysqlDB::getDB(self::$defaultInstance);
        return $db->get("$trackAndroid(a)", [
            "[><]$trackUser(u)" => ['a.id' => 'platform_id']
        ], [
            "a.id",
            "a.ad_channel",
            "a.imei",
            "a.imei_hash",
            "a.android_id",
            "a.android_id_hash",
            "a.oaid",
            "a.callback",
            "a.create_time",
            "a.init_time",
            "u.id(tu_id)",
            "u.platform_type",
            "u.platform_id",
            "u.channel_id",
            "u.ad_id",
            "u.user_id",
            "u.track_state",
            "u.deep_state",
            "u.create_time",
            "u.active_time",
            "u.register_time",
            "u.trial_pay_time",
        ], $whereNew);
    }
}