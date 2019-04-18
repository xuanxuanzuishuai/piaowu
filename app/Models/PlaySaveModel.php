<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/2/22
 * Time: 3:03 PM
 *
 * 演奏存档
 */

namespace App\Models;

use App\Libs\MysqlDB;

class PlaySaveModel extends Model
{

    public static $table = 'play_save';

    public static function getByOpern($userID, $opernID)
    {
        $db = MysqlDB::getDB();
        return $db->get(self::$table, '*', ['student_id' => $userID, 'lesson_id' => $opernID]);
    }
}