<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/2/28
 * Time: 5:30 PM
 */

namespace App\Models;


use App\Libs\Constants;
use App\Libs\RedisDB;

class BannerModel extends Model
{
    protected static $table = 'banner';

    const CACHE_KEY = 'banner_cache';
    const CACHE_EXPIRE = 14400; // 缓存 4h刷新

    public static function getBanner()
    {
        $redis = RedisDB::getConn();
        $banner = $redis->get(self::CACHE_KEY);
        if (true || empty($banner)) {
            $banner = self::getValidBanner();
            $redis->setex(self::CACHE_KEY, self::CACHE_EXPIRE, json_encode($banner));
        } else {
            $banner = json_decode($banner, true);
        }

        return $banner;
    }

    public static function getValidBanner()
    {
        $now = time();
        $banner = self::getRecords([
            'status' => Constants::STATUS_TRUE,
            'start_time[<=]' => $now,
            'end_time[>=]' => $now,
            'ORDER' => ['sort' => 'ASC']
        ], '*', false);
        return $banner ?? [];
    }
}