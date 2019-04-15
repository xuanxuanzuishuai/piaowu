<?php

/*
 * Author: Yunchao Chen
 * */

namespace App\Services;

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

    public static function getById($cRId) {
        return ClassroomModel::getById($cRId);
    }

    public static function getClassrooms() {
        return ClassroomModel::getClassrooms();
    }
}