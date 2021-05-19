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

class TemplatePosterModel extends Model
{
    //表名称
    public static $table = "template_poster";

    const DISABLE_STATUS = 1; //下线
    const NORMAL_STATUS = 2; //上线

    const INDIVIDUALITY_POSTER = 1; //个性化海报
    const STANDARD_POSTER = 2; //标准海报

    /**
     * @param $params
     * @return array
     * 海报模板图列表
     */
    public static function getList($params)
    {
        $db = MysqlDB::getDB();
        $totalCount = self::getTotalCount($params);
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
               self::$table . '.name',
               self::$table . '.poster_path',
               self::$table . '.status',
               self::$table . '.order_num',
               self::$table . '.update_time',
               self::$table . '.example_path',
            ],
            [
                'type' => $params['type'],
                'ORDER' => [
                    'order_num' => 'ASC',
                    'update_time' => 'DESC'
                ],
                'LIMIT' => [($pageId - 1) * $pageLimit, $pageLimit]
            ]);
        return [$res, $pageId, $pageLimit, $totalCount];
    }

    public static function getTotalCount($params)
    {
        $db = MysqlDB::getDB();
        return $db->count(self::$table,
            [
                'type' => $params['type'],
            ]);
    }
}