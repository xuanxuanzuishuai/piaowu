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
    
    /**
     * @param $id
     * @return array
     * 获取正在使用该海报的月月有奖,周周有奖活动
     */
    public static function getActivityByPosterId($id)
    {
    
        $resWeek = self::getActivityByPidAndType($id, 'week');
        $resMonth = self::getActivityByPidAndType($id, 'month');
        return [$resWeek, $resMonth];
    }
    
    /**
     * @param $id
     * @param $type
     * @return array|null
     * 获取正在使用该海报的月月有奖,周周有奖活动
     */
    public static function getActivityByPidAndType($id, $type)
    {
        $time = time();
        $table1 = self::$table;
        $table2 = ActivityPosterModel::$table;
        $table3 = '';
        if ($type == 'week') {
            $table3 = WeekActivityModel::$table;
        }
        if ($type == 'month') {
            $table3 = MonthActivityModel::$table;
        }
        if (empty($table3)) {
            return [];
        }
        $status1 = self::NORMAL_STATUS;
        $status2 = ActivityPosterModel::NORMAL_STATUS;
        $status31 = OperationActivityModel::ENABLE_STATUS_OFF;
        $status32 = OperationActivityModel::ENABLE_STATUS_ON;
        $sql = "
            SELECT
                {$table2}.id,{$table2}.activity_id
            FROM
                {$table1}
                INNER JOIN {$table2} ON {$table1}.id = {$table2}.poster_id
                INNER JOIN {$table3} ON {$table2}.activity_id = {$table3}.activity_id
            WHERE
                {$table1}.id = {$id}
                AND {$table1}.`status` = {$status1}
                AND {$table2}.`status` = {$status2}
                AND {$table3}.enable_status IN ( {$status31}, {$status32} )
                AND {$table3}.end_time > {$time}
        ";
        $db = MysqlDB::getDB();
        $res = $db->queryAll($sql);
        return $res;
    }
}