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
        return self::getRecords(["ORDER" => ["create_time" => "DESC"]]);
    }

    public static function insertCampus($insert) {
        return self::insertRecord($insert);
    }

    public static function updateCampus($id, $update) {
        $result = self::updateRecord($id, $update);
        return ($result && $result > 0);
    }
}