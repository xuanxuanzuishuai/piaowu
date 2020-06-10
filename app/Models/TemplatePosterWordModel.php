<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/06/11
 * Time: 5:14 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\Util;

class TemplatePosterWordModel extends Model
{
    //表名称
    public static $table = "template_poster_word";
    const DISABLE_STATUS = 1; //下线
    const NORMAL_STATUS = 2; //上线

    public static function getList($params)
    {
        $db = MysqlDB::getDB();
        $totalCount = self::getTotalCount();
        if ($totalCount == 0) {
            return [[],1,0,0];
        }
        list($pageId, $pageLimit) = Util::appPageLimit($params);
        $res = $db->select(self::$table,
            [
                '[>]' . EmployeeModel::$table => ['operate_id' => 'id']
            ],
            [
                EmployeeModel::$table . '.name(operator_name)',
                self::$table . '.id',
                self::$table . '.content',
                self::$table . '.status',
                self::$table . '.update_time'
            ],
            [
                'ORDER' => [
                    'update_time' => 'DESC'
                ],
                'LIMIT' => [($pageId - 1) * $pageLimit, $pageLimit]
            ]);
        return [$res, $pageId, $pageLimit, $totalCount];
    }

    public static function getTotalCount()
    {
        $db = MysqlDB::getDB();
        return $db->count(self::$table);
    }
}