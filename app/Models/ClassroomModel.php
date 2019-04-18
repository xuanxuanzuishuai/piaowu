<?php

/*
 * Author: Yunchao Chen
 * */

namespace App\Models;

class ClassroomModel extends Model
{
    public static $table = "classroom";

    /**
     * @param $insert
     * @return int|mixed|string|null
     */
    public static function insertClassroom($insert) {
        return self::insertRecord($insert);
    }

    /**
     * @param $id
     * @param $update
     * @return bool
     */
    public static function updateClassroom($id, $update) {
        $result = self::updateRecord($id, $update);
        return ($result && $result > 0);
    }

    /**
     * @param null $params
     * @return array
     */
    public static function getClassrooms($params = null) {
        $where = [];
        if(!empty($params['campus_id'])) {
            $where['campus_id'] = $params['campus_id'];
        }
        return self::getRecords($where);
    }
}