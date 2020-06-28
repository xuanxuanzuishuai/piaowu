<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/6/22
 * Time: 5:12 PM
 */

namespace App\Models;


use App\Libs\ListTree;
use App\Libs\MysqlDB;
use App\Libs\RedisDB;

class DeptModel extends Model
{
    public static $table = 'dept';
    public static $treeCacheKey = 'dept_tree';
    public static $treeCacheExpire = 86400 * 7;

    public static function list($where, $page, $count)
    {
        $db = MysqlDB::getDB();

        $total = $db->count(self::$table . '(d)', $where);
        if ($total < 1) {
            return [[], 0];
        }

        $where["LIMIT"]  = [($page - 1) * $count, $count];

        $records = $db->select(self::$table . '(d)', [
            '[>]' . self::$table . '(p)' => ['d.parent_id' => 'id'],
            '[>]' . EmployeeModel::$table . '(e)' => ['d.operator' => 'id']
        ], [
            'd.id',
            'd.name',
            'd.parent_id',
            'p.name(parent_name)',
            'd.operator',
            'e.name(operator_name)',
            'd.create_time',
            'd.update_time',
            'd.status',
        ], $where);

        return [$records, $total];
    }

    public static function getTree()
    {
        $redis = RedisDB::getConn();

        $cacheKey = self::$treeCacheKey;
        $cache = $redis->get($cacheKey);
        $treeData = json_decode($cache, true);

        if (empty($treeData)) {
            $list = self::getRecords(['status' => 1]);
            $listTree = new ListTree($list, 'id', 'parent_id');
            $treeData = $listTree->tree['subs'];

            $redis->setex($cacheKey, self::$treeCacheExpire, json_encode($treeData));
        }

        return $treeData;
    }

    public static function delTreeCache()
    {
        $redis = RedisDB::getConn();
        $redis->del(self::$treeCacheKey);
    }

    public static function insertRecord($data, $isOrg = true)
    {
        $ret = parent::insertRecord($data);
        self::delTreeCache();

        return $ret;
    }

    public static function updateRecord($id, $data, $isOrg = true)
    {
        $ret = parent::updateRecord($id, $data);
        self::delTreeCache();

        return $ret;
    }
}