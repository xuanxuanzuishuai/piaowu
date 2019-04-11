<?php
/**
 * Created by PhpStorm.
 * User: liliang
 * Date: 18/7/19
 * Time: 上午11:51
 */

namespace App\Models;


use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\Util;

class DeptModel extends Model
{
    const MAX_ID = 10000;

    public static $table = "dept";
    public static $redisExpire = 0;
    public static $redisDB;

    const RELATION_SEPRATOR = "/";

    /**
     * 保存部门信息
     * @param $insert
     * @return mixed
     */
    public static function insertDept($insert)
    {
        $db = MysqlDB::getDB();
        return $db->insertGetID(self::$table, $insert);
    }

    /**
     * 修改部门信息
     * @param $id
     * @param $update
     * @return int
     */
    public static function updateDept($id, $update)
    {
        $result = self::updateRecord($id, $update);
        return ($result && $result > 0);
    }

    /**
     * 获取部门信息
     * @return mixed
     */
    public static function getList()
    {
        $deptList = MysqlDB::getDB()->select(self::$table, [
            '[>]' . self::$table . '(parent_dept)' => ['parent_id' => 'id'],
            '[>]' . DeptDataModel::$table => ['id' => 'dept_id']
        ], [
            self::$table . '.id',
            self::$table . '.dept_name',
            self::$table . '.relation',
            self::$table . '.parent_id',
            self::$table . '.create_time',
            self::$table . '.status',
            'parent_dept.dept_name(p_dept_name)',
            DeptDataModel::$table . '.dept_ids',
            DeptDataModel::$table . '.data_type'
        ]);
        return $deptList;
    }

    /**
     * 获取部门子部门以及自己
     * @param $relation
     * @return array
     */
    public static function getChildren($relation)
    {
        return MysqlDB::getDB()->select(self::$table, 'id', ['relation[~]' => $relation . '%']);
    }

    /**
     * 获取dept
     * @param $fields
     * @param $where
     * @return mixed
     */
    public static function getDeptInfo($fields, $where)
    {
        return MysqlDB::getDB()->select(self::$table, $fields, $where);
    }

    /**
     * 获取数量
     * @param null $where
     * @return number
     */
    public static function getCount($where = null)
    {
        return MysqlDB::getDB()->count(self::$table, $where);
    }

    /**
     * 按名称模糊查询部门（带分页）
     * @param $deptName
     * @param $page
     * @param $pageSize
     * @return array
     */
    public static function getDeptsByName($deptName, $page, $pageSize)
    {
        $where = [];
        // 搜索条件 部门名称
        if (!empty($deptName)) {
            $where['AND'][DeptModel::$table . '.dept_name[~]'] = Util::sqlLike($deptName);
        }

        $totalCount = MysqlDB::getDB()->count(self::$table, $where);
        if ($totalCount == 0) {
            return array($totalCount, []);
        }

        if ($page > 0 && $pageSize > 0) {
            $where['LIMIT'] = [($page - 1) * $pageSize, $pageSize];
        }

        $where[DictModel::$table . '.type'] = Constants::DICT_TYPE_NORMAL_OR_INVALID;

        $deptList = MysqlDB::getDB()->select(self::$table, [
            '[>]' . self::$table . '(parent_dept)' => ['parent_id' => 'id'],
            '[>]' . DeptDataModel::$table => ['id' => 'dept_id'],
            '[>]' . DictModel::$table => ['key_code' => 'status']
        ], [
            self::$table . '.id',
            self::$table . '.dept_name',
            self::$table . '.relation',
            self::$table . '.parent_id',
            self::$table . '.create_time',
            self::$table . '.status',
            DictModel::$table . '.key_value(status_str)',
            'parent_dept.dept_name(p_dept_name)',
            DeptDataModel::$table . '.dept_ids',
            DeptDataModel::$table . '.data_type'
        ], $where);

        return array($totalCount, $deptList);
    }

}