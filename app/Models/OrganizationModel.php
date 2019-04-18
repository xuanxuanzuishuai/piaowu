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
        list($page, $count) = Util::formatPageCount(['page' => $page,'count' => $count]);

        $db = MysqlDB::getDB();
        $records = $db->select(self::$table,'*', [
            'ORDER' => ['create_time' => 'DESC'],
            'LIMIT' => [($page - 1) * $count,$count]
        ]);
        $total = $db->count(self::$table);

        return [$records, $total];
    }
}