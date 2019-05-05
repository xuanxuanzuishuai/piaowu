<?php

/*
 * Author: Yunchao Chen
 * */

namespace App\Models;

use App\Libs\MysqlDB;

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
        $where= [];
        global $orgId;
        if($orgId > 0) {
            $where['cr.org_id'] = $orgId;
        }
        if(!empty($params['campus_id'])) {
            $where['cr.campus_id'] = $params['campus_id'];
        }
        $db = MysqlDB::getDB();
        $join = [
            '[><]'.CampusModel::$table." (c)" => ['cr.campus_id'=>'id']
        ];

        return $db->select(self::$table." (cr)",$join,[
            'cr.id',
            'cr.name',
            'cr.desc',
            'cr.pic_url',
            'cr.campus_id',
            'c.name (campus_name)'
        ],$where);
    }
}