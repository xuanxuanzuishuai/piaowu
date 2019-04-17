<?php

/*
 * Author: Yunchao Chen
 * */

namespace App\Services;

use App\Libs\Util;
use App\Models\CampusModel;


class CampusService
{
    public static function insertOrUpdate($campusId, $param) {
        $campus = CampusModel::getById($campusId);

        $update = [
            "name" => $param["name"],
            "address" => $param["address"],
            "desc" => $param["desc"],
            "pic_url" => $param["pic_url"]
        ];

        if (empty($campus)) {
            $update["create_time"] = time();
            return CampusModel::insertCampus($update);
        }

        CampusModel::updateCampus($campusId, $update);
        return $campusId;
    }

    public static function getById($campusId) {
        return CampusModel::getById($campusId);
    }

    public static function getCampus() {
        $res =  CampusModel::getCampus();
        foreach($res as $key => $value) {
            if(!empty($value['pic_url']))
            $res[$key]['pic_url'] = Util::getQiNiuFullImgUrl($value['pic_url']);
        }
        return $res;
    }
}