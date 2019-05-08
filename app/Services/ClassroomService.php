<?php

/*
 * Author: Yunchao Chen
 * */

namespace App\Services;

use App\Libs\AliOSS;
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
        $classroom = ClassroomModel::getClassroomDetail($cRId);
        if(!empty($classroom['pic_url'])) {
            $classroom['signed_pic_url'] = AliOSS::signUrls($classroom['pic_url']);
        }
        return $classroom;
    }

    public static function getClassrooms($params = null) {
        $res =  ClassroomModel::getClassrooms($params);
        $res = AliOSS::signUrls($res, 'pic_url', 'signed_pic_url');
        return $res;
    }

    public static function getById($cRId)
    {
        $classroom = ClassroomModel::getById($cRId);
        if(!empty($classroom['pic_url'])) {
            $classroom['signed_pic_url'] = AliOSS::signUrls($classroom['pic_url']);
        }
        return $classroom;
    }
}