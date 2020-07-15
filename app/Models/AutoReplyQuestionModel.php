<?php
namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\Util;

class AutoReplyQuestionModel extends Model
{
    //表名称
    public static $table = "wx_question";


    public static function getTotalCount($key)
    {
        $db = MysqlDB::getDB();
        if (empty($key)) {
            return $db->count(self::$table);
        }
        return $db->count(self::$table,['title[~]' => Util::sqlLike($key)]);
    }
}