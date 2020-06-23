<?php
namespace App\Models;


use App\Libs\MysqlDB;

class AutoReplyQuestionModel extends Model
{
    //表名称
    public static $table = "wx_question";


    public static function getTotalCount()
    {
        $db = MysqlDB::getDB();
        return $db->count(self::$table,
            [
                'status' => 1,
            ]);
    }
}