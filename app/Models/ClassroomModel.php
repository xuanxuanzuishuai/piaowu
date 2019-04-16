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

    public static function getClassrooms($params = null) {
        $db = MysqlDB::getDB();
        $where = [];
        if(!empty($params['campus_id'])) {
            $where['campus_id'] = $params['campus_id'];
        }
        return $db->select(self::$table, "*",$where);
    }
}