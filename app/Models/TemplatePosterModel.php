<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/06/11
 * Time: 5:14 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;

class TemplatePosterModel extends Model
{
    //表名称
    public static $table = "template_poster";

    const DISABLE_STATUS = 1; //下线
    const NORMAL_STATUS = 2; //上线

    const INDIVIDUALITY_POSTER = 1; //个性化海报
    const STANDARD_POSTER = 2; //标准海报

    const STANDARD_POSTER_TXT = '标准海报';

    //练琴数据 1需要 2不需要
    const PRACTISE_WANT = 1;
    const PRACTISE_NOT_WANT = 2;

    const POSTER_ORDER = 2; //海报列表排序 1.个性海报在前(默认) 2.标准海报在前

    //练琴数据展示文案
    public static $practiseArray = [
        1 => '是',
        2 => '否',
    ];
    
    /**
     * 海报模板图列表
     * @param $params
     * @param $pageId
     * @param $pageLimit
     * @return array
     */
    public static function getList($params, $pageId, $pageLimit)
    {
        $db = MysqlDB::getDB();
        
        $where = [
            self::$table . '.type' => $params['type'],
        ];
        if (!empty($params['status']) && in_array($params['status'], [self::DISABLE_STATUS, self::STANDARD_POSTER])) {
            $where[self::$table . '.status'] = $params['status'];
        }
        if (!empty($params['app_id'])) {
            $where[self::$table . '.app_id'] = $params['app_id'];
        }
        $totalCount = $db->count(
            self::$table,
            $where
        );
        if ($totalCount == 0) {
            return [[], 0];
        }
        
        $res = $db->select(
            self::$table,
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
               self::$table . '.practise',
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
        return [$res, $totalCount];
    }

    /**
     * 获取海报模板详情
     * @param $id
     * @param int $status
     * @return mixed
     */
    public static function getPosterInfo($id, $status = self::NORMAL_STATUS)
    {
        return self::getRecord(['id' => $id, 'status' => $status]);
    }
}
