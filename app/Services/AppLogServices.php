<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/27
 * Time: 2:41 PM
 */

namespace App\Services;


use App\Models\AppLogModel;

class AppLogServices
{
    public static function locationLog($orgId, $location)
    {
        $data = [
            'org_id' => $orgId,
            'create_time' => time(),
            'location' => $location['location'] ?? '',
            'province' => $location['province'] ?? '',
            'city' => $location['city'] ?? '',
            'district' => $location['district'] ?? '',
        ];

        AppLogModel::insertRecord($data, false);
    }
}