<?php
namespace App\Models;

use App\Libs\MysqlDB;

class RegionProvinceRelationModel extends Model
{
    public static $table = "region_province_relation";

    public static function getRelateProvince($regionId)
    {
        $sql = 'select r.region_id, p.province_name from region_province_relation r 
left join area_province p on r.province_id = p.id where r.region_id = ' . $regionId;

        return MysqlDB::getDB()->queryAll($sql);
    }
}
