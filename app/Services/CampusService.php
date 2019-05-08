<?php

/*
 * Author: Yunchao Chen
 * */

namespace App\Services;

use App\Libs\AliOSS;
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
        $campus = CampusModel::getById($campusId);
        if(!empty($campus['pic_url'])) {
            $campus['signed_pic_url'] = AliOSS::signUrls($campus['pic_url']);
        }
        return $campus;
    }

    public static function getCampus() {
        $res =  CampusModel::getCampus();
        $res = AliOSS::signUrls($res, 'pic_url', 'signed_pic_url');
        return $res;
    }
}