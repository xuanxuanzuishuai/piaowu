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
        $totalCount = $db->count(self::$table);
        list($pageId, $pageLimit) = Util::appPageLimit($params);
        if ($totalCount == 0) {
            return [[], $pageId, $pageLimit, 0];
        }
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
                    'status' => 'DESC',
                    'update_time' => 'DESC'
                ],
                'LIMIT' => [($pageId - 1) * $pageLimit, $pageLimit]
            ]);
        return [$res, $pageId, $pageLimit, $totalCount];
    }

}