<?php

/*
 * Author: Yunchao Chen
 * */

namespace App\Services;

use App\Libs\Util;
use App\Models\ClassroomModel;


class ClassroomService
{
    public static function insertOrUpdateClassroom($cRId, $param) {
        $classroom = ClassroomModel::getById($cRId);
        $update = [
            "name" => $param["name"],
            "campus_id" => $param["campus_id"],
            "desc" => $param["desc"],
            "pic_url" => $param["pic_url"]
        ];

        if (empty($classroom)) {
            return ClassroomModel::insertClassroom($update);
        }

        ClassroomModel::updateRecord($cRId, $update);
        return $cRId;
    }

    public static function getClassroomDetail($cRId) {
        return ClassroomModel::getClassroomDetail($cRId);
    }

    public static function getClassrooms($params = null) {
        $res =  ClassroomModel::getClassrooms($params);
        foreach($res as $key => $value) {
            if(!empty($value['pic_url']))
                $res[$key]['pic_url'] = Util::getQiNiuFullImgUrl($value['pic_url']);
        }
        return $res;
    }
}