<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/10/22
 * Time: 下午6:12
 */

namespace App\Models;
use App\Libs\RedisDB;
use App\Libs\Constants;

class QuestionTagModel extends Model
{
    public static $table = 'question_tag';
    private static $tagCacheKey = 'question_tags';

    private static function match($tags, $key) {
        if(empty($key)) {
            return $tags;
        } else {
            $matches = [];
            foreach($tags as $tag) {
                if(mb_strstr($tag['tag'], $key)) {
                    $matches[] = $tag;
                }
            }
            return $matches;
        }
    }

    public static function tags($key)
    {
        $conn = RedisDB::getConn();
        $tags = $conn->get(self::$tagCacheKey);
        if(empty($tags)) {
            $tags = self::getRecords(['status' => Constants::STATUS_TRUE], ['id', 'tag'], false);
            if(empty($tags)) {
                return [];
            }
            $conn->set(self::$tagCacheKey, json_encode($tags, 1));
            $conn->expire(self::$tagCacheKey, 86400 * 7); // expired after one week
        } else {
            $tags = json_decode($tags, 1);
        }

        return self::match($tags, $key);
    }

    public static function delTagCacheKey()
    {
        $conn = RedisDB::getConn();
        $conn->del(self::$tagCacheKey);
    }
}