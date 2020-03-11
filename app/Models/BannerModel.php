<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/2/28
 * Time: 5:30 PM
 */

namespace App\Models;


use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\RedisDB;

class BannerModel extends Model
{
    protected static $table = 'banner';

    const CACHE_KEY = 'banner_cache';
    const CACHE_EXPIRE = 14400; // 缓存 4h刷新

    const ACTION_MINI_PRO = 1; // 跳转小程序
    const ACTION_HREF = 2; // 跳转网页

    public static function getBanner()
    {
        $redis = RedisDB::getConn();
        $banner = $redis->get(self::CACHE_KEY);
        if (true || empty($banner)) {
            $banner = self::getValidBanner();
            $redis->setex(self::CACHE_KEY, self::CACHE_EXPIRE, json_encode($banner));
        } else {
            $banner = json_decode($banner, true);
        }

        return $banner;
    }

    public static function getValidBanner()
    {
        $now = time();
        $banner = self::getRecords([
            'status' => Constants::STATUS_TRUE,
            'start_time[<=]' => $now,
            'end_time[>=]' => $now,
            'ORDER' => ['sort' => 'ASC']
        ], '*', false);
        return $banner ?? [];
    }

    /**
     * 获取banner列表数据
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function getList($params, $page, $count)
    {
        $where = self::formatListParams($params);
        $table = self::$table . '(b)';
        $join = [
            '[>]' . EmployeeModel::$table . '(e)' => ['b.operator' => 'id'],
        ];
        $count = self::getListCount($table, $join, $where);
        if(empty($count)){
            return ['count' => 0, 'data'=>[]];
        }
        $data = self::getListData($table, $join, $where, $page, $count);
        return ['count' => $count, 'data' => $data];
    }

    /**
     * 格式化搜索条件
     * @param $params
     * @return array
     */
    public static function formatListParams($params)
    {
        $where = [];
        if(!empty($params['name'])){
            $where['b.name[~]'] = $params['name'];
        }
        if(!empty($params['start_time'])){
            $where['b.start_time[<=]'] = $params['start_time'];
        }
        if(!empty($params['end_time'])){
            $where['b.end_time[>=]'] = $params['end_time'];
        }
        return $where;
    }

    /**
     * 获取列表count
     * @param $table
     * @param $join
     * @param $where
     * @return number
     */
    public static function getListCount($table, $join, $where)
    {
        return MysqlDB::getDB()->count($table, $join, '*', $where);
    }

    /**
     * 获取数据
     * @param $table
     * @param $join
     * @param $where
     * @param $page
     * @param $count
     * @return array
     */
    public static function getListData($table, $join, $where, $page, $count)
    {
        $where['LIMIT'] = [($page - 1) * $count, $count];
        $field = [
            'b.id',
            'b.name',
            'b.desc',
            'b.create_time',
            'b.start_time',
            'b.end_time',
            'b.status',
            'b.operator',
            'b.sort',
            'b.show_main',
            'b.image_main',
            'b.show_list',
            'b.image_list',
            'b.action_type',
            'b.action_detail',
            'b.filter',
            'e.name(operator_name)'
        ];
        return MysqlDB::getDB()->select($table, $join, $field, $where);
    }
}