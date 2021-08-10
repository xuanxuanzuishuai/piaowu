<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/06/11
 * Time: 5:14 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\RedisDB;
use App\Libs\Util;

class TemplatePosterWordModel extends Model
{
    //表名称
    public static $table = "template_poster_word";

    const DISABLE_STATUS = 1; //下线
    const NORMAL_STATUS = 2; //上线

    const KEY_WORD_LIST = 'TEMPLATE_POSTER_WORD';

    public static function getList($params)
    {
        $db = MysqlDB::getDB();
        $totalCount = $db->count(self::$table);
        list($pageId, $pageLimit) = Util::appPageLimit($params);
        if ($totalCount == 0) {
            return [[], $pageId, $pageLimit, 0];
        }
        $where = [
            'ORDER' => [
                'status' => 'DESC',
                'update_time' => 'DESC'
            ],
            'LIMIT' => [($pageId - 1) * $pageLimit, $pageLimit]
        ];
        if (!empty($params['status'])) {
            $where[self::$table . '.status'] = $params['status'];
        }
        $res = $db->select(
            self::$table,
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
            $where
        );
        return [$res, $pageId, $pageLimit, $totalCount];
    }

    /**
     * 前端调用文案列表
     * @param $params
     * @return array|mixed
     */
    public static function getFrontList($params)
    {
        $redis = RedisDB::getConn();
        $status = $params['status'] ?: self::NORMAL_STATUS;
        $cacheKey = self::KEY_WORD_LIST . $status;
        $cache = $redis->get($cacheKey);
        if (!empty($cache)) {
            return json_decode($cache, true);
        }

        $list = self::getRecords(
            [
                'status' => $status,
                'ORDER' => ['update_time' => 'DESC']
            ]
        );
        if (!empty($list)) {
            $redis->setex($cacheKey, Util::TIMESTAMP_ONEDAY, json_encode($list));
        }
        return $list;
    }

    /**
     * 清除前端展示缓存
     */
    public static function delWordListCache()
    {
        $arr = [
            self::NORMAL_STATUS,
            self::DISABLE_STATUS
        ];
        foreach ($arr as $value) {
            RedisDB::getConn()->del([self::KEY_WORD_LIST . $value]);
        }
     }

    /**
     * 格式化数据
     * @param $item
     * @return mixed
     */
    public static function formatOne($item)
    {
        if (!empty($item['content'])) {
            $item['content'] = Util::textDecode($item['content']);
        }
        return $item;
    }
}
