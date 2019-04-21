<?php
/**
 * Created by PhpStorm.
<<<<<<< HEAD
 * User: mncu
 * Date: 2019/4/16
 * Time: 16:30
 */
namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\Util;
//use Intervention\Image\ImageManagerStatic as Image;

class OrganizationModel extends Model
{
    public static $table = "organization";

    const STATUS_NORMAL = 1; //正常
    const STATUS_STOP = 0; //停用

    /**
     * 查询机构列表
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function selectOrgList($page, $count, $params)
    {
        $limit = Util::limitation($page, $count);

        $db = MysqlDB::getDB();
        $records = $db->queryAll("select o.*,e.name operator_name,o2.name parent_name from organization o
        left join employee e on o.operator_id = e.id
        left join organization o2 on o.parent_id = o2.id {$limit}");
        $total = $db->count(self::$table);

        return [$records, $total];
    }

    /**
     *
     */
    public static function generateQrCode(){

    }
}
