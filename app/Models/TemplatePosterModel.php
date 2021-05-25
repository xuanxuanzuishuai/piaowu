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

        $where = [
            self::$table . '.type' => $params['type'],
        ];
        if (!empty($params['status']) && in_array($params['status'], [self::DISABLE_STATUS, self::STANDARD_POSTER])) {
            $where[self::$table . '.status'] = $params['status'];
        }
        $totalCount = $db->count(
            self::$table,
            $where
        );
    
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
               self::$table . '.name',
               self::$table . '.poster_path',
               self::$table . '.status',
               self::$table . '.order_num',
               self::$table . '.update_time',
               self::$table . '.example_path',
            ],
            array_merge([
                'ORDER' => [
                    'order_num' => 'ASC',
                    'update_time' => 'DESC'
                ],
                'LIMIT' => [($pageId - 1) * $pageLimit, $pageLimit]
            ], $where)
        );
        return [$res, $pageId, $pageLimit, $totalCount];
    }

}