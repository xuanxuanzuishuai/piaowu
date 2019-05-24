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
     * @param $user_id
     * @return int|mixed|null|string
     */
    public static function insertReferee($referee_id, $user_id) {
        $db = MysqlDB::getDB();
        //得到referee_id解密后的user_id
        $refereeInfo = UserQrTicketModel::getRecord(['qr_ticket' => $referee_id], ['user_id','type'], false);
        if (!empty($refereeInfo)) {
            return $db->insertGetID(self::$table, [
                "referee_id" => $refereeInfo['user_id'],
                "user_id" => $user_id,
                "referee_type" => $refereeInfo['type'],
                "create_time" => time()
            ]);
        }
    }
}