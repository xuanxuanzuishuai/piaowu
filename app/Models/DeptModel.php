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
    public static $listCacheKey = 'dept_list';
    public static $listCacheExpire = 86400 * 7;

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
        $list = self::getList();
        $lt = new ListTree($list);

        return $lt->tree['subs'];
    }

    public static function getList()
    {
        $redis = RedisDB::getConn();

        $cache = $redis->get(self::$listCacheKey);
        $cacheData = json_decode($cache, true);

        if (empty($cacheData)) {
            $cacheData = self::updateCache();
        }

        return $cacheData;
    }

    public static function updateCache()
    {
        $redis = RedisDB::getConn();

        $list = self::getRecords(['status' => 1]);
        $redis->setex(self::$listCacheKey, self::$listCacheExpire, json_encode($list));

        return $list;
    }

    public static function delCache($id, $pri = null)
    {
        parent::delCache($id, $pri);

        $redis = RedisDB::getConn();
        $redis->del([self::$listCacheKey]);
    }

    public static function insertRecord($data, $isOrg = true)
    {
        $ret = parent::insertRecord($data, $isOrg);
        self::delCache(0);
        return $ret;
    }

    /**
     * 通过部门ID获取此部门下的所有子部门
     * @param $deptId
     * @return array|null
     */
    public static function getSubDeptById($deptId)
    {
        $sql = "WITH recursive dept_paths AS (
                    SELECT
                        id,
                        NAME,
                        parent_id
                    FROM
                        dept
                    WHERE
                        parent_id = ".$deptId." UNION ALL
                    SELECT
                        dept.id,
                        dept.NAME,
                        dept.parent_id
                    FROM
                        dept
                        INNER JOIN dept_paths ON dept_paths.id = dept.parent_id
                    ) SELECT
                    *
                FROM
                    dept_paths";
        $db = MysqlDB::getDB();
        return $db->queryAll($sql);
    }
}