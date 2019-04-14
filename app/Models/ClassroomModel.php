<?php

/*
 * Author: Yunchao Chen
 * */

namespace App\Models;

use App\Libs\MysqlDB;


class ClassroomModel extends Model
{
    public static $table = "classroom";

    public static function insertClassroom($insert) {
        $db = MysqlDB::getDB();
        return $db->insertGetID(self::$table, $insert);
    }

    public static function updateClassroom($id, $update) {
        $result = self::updateRecord($id, $update);
        return ($result && $result > 0);
    }

    public static function getClassrooms() {
        $db = MysqlDB::getDB();
        return $db->select(self::$table, "*");
    }
}