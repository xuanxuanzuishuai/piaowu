<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/20
 * Time: 12:02
 */
namespace App\Models;

use App\Libs\MysqlDB;


class UserWeixinModel extends Model
{
    public static $table = "user_weixin";

    const STATUS_NORMAL = 1;

    const USER_TYPE_STUDENT = 1; // 学生
    const USER_TYPE_TEACHER = 2;  // 老师
    const USER_TYPE_STUDENT_ORG = 3; // 学生机构号

    const BUSI_TYPE_STUDENT_SERVER = 1; // 学生服务号
    const BUSI_TYPE_TEACHER_SERVER = 2; // 老师服务号

    const BUSI_TYPE_EXAM_MINAPP = 6; // 音基小程序

    /** 获取最近绑定的该open_id的信息
     * @param $open_id
     * @return array
     */
    public static function getBoundInfoByOpenId($open_id) {
        $where = [self::$table .".open_id" => $open_id, self::$table .".status" => self::STATUS_NORMAL];

        $result = MysqlDB::getDB()->get(self::$table, "*", $where);
        return $result;
    }

    public static function getBoundInfoByUserId($user_id, $app_id, $user_type, $busi_type){
        $where = [
            self::$table .".user_id" => $user_id,
            self::$table .".status" => self::STATUS_NORMAL,
            self::$table .".app_id" => $app_id,
            self::$table .".user_type" => $user_type,
            self::$table .".busi_type" => $busi_type,
            "ORDER" => ["id" => "DESC"]];

        $result = MysqlDB::getDB()->get(self::$table, "*", $where);
        return $result;
    }

    public static function boundUser($open_id, $user_id, $app_id, $user_type, $busi_type){
        $db = MysqlDB::getDB();
        // 首先删除该open_id的绑定关系
        $db->delete(self::$table, [
            "open_id" => $open_id
        ]);
        return $db->insertGetID(self::$table, [
            "open_id" => $open_id,
            "user_id" => $user_id,
            "app_id" => $app_id,
            "user_type" => $user_type,
            "busi_type" => $busi_type,
            "status" => self::STATUS_NORMAL,
        ]);
    }
}