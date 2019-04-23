<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/23
 * Time: 15:59
 */
namespace App\Models;

use App\Libs\MysqlDB;


class UserRefereeModel extends Model
{
    public static $table = "user_referee";

    /**
     * @param $referee_id
     * @param $referee_type
     * @param $user_id
     * @return int|mixed|null|string
     */
    public static function insertReferee($referee_id, $referee_type, $user_id) {
        $db = MysqlDB::getDB();
        return $db->insertGetID(self::$table, [
            "referee_id" => $referee_id,
            "user_id" => $user_id,
            "referee_type" => $referee_type,
            "create_time" => time()
        ]);
    }
}