<?php

/*
 * Author: Yunchao Chen
 * */

namespace App\Models;

use App\Libs\MysqlDB;


class CampusModel extends Model
{
    public static $table = "campus";

    public static function getCampus() {
        $where = MysqlDB::addOrgId(["ORDER" => ["create_time" => "DESC"]]);

        $db = MysqlDB::getDB();
        return $db->select(self::$table, "*", $where);
    }

    public static function insertCampus($insert) {
        $insert = MysqlDB::addOrgId($insert);
        $db = MysqlDB::getDB();
        return $db->insertGetID(self::$table, $insert);
    }

    public static function updateCampus($id, $update) {
        $result = self::updateRecord($id, $update);
        return ($result && $result > 0);
    }
}