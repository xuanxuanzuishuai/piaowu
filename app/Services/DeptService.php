<?php
/**
 * Created by PhpStorm.
 * User: liliang
 * Date: 2018/07/19
 * Time: 上午11:36
 */

namespace App\Services;

use App\Models\DeptModel;

class DeptService
{

    /**
     * 查询部门列表
     * @param $deptName
     * @param $page
     * @param $pageSize
     * @return mixed
     */
    public static function getDeptListService($deptName, $page, $pageSize)
    {
        list($totalCount,$depts) = DeptModel::getDeptsByName($deptName, $page, $pageSize);

        return [$totalCount, $depts];
    }

    /**
     * 获取部门树
     * @param int $pid
     * @return array
     */
    public static function getDeptTree($pid = 0)
    {
        $nodes = DeptModel::getList();

        $tree = self::make_tree($nodes, 'id', 'parent_id', '_child', $pid);

        return $tree;

    }

    /**
     * 构造树结构
     * @param $list
     * @param string $pk
     * @param string $pid
     * @param string $child
     * @param int $root
     * @return array
     */
    public static function make_tree($list, $pk = 'id', $pid = 'parent_id', $child = '_child', $root = 0)
    {
        $tree = array();
        foreach ($list as $key => $val) {
            if ($val[$pid] == $root) {
                unset($list[$key]);
                if (!empty($list)) {
                    $child = self::make_tree($list, $pk, $pid, $child, $val[$pk]);
                    if (!empty($child)) {
                        $val['_child'] = $child;
                    }
                }
                $tree[] = $val;
            }
        }
        return $tree;
    }

    /**
     * 获取当前部门及其子孙部门
     * @param $deptId
     * @param $withSelf
     * @return array
     */
    public static function getChild($deptId, $withSelf = true)
    {
        $children = [];
        $relation = self::getRelation($deptId);

        if (!empty($relation)) {
            $children = DeptModel::getChildren($relation);
            //排除当前部门
            if ($withSelf == false) unset($children[array_search($deptId, $children)]);
        }
        return $children;
    }

    /**
     * 获取当前部门父部门id
     * @param $deptId
     * @return null
     */
    public static function getParent($deptId)
    {
        $res = DeptModel::getById($deptId);
        return empty($res['parent_id']) ? null : $res['parent_id'];
    }

    /**
     * 获取relation path
     * @param $deptId
     * @return null
     */
    public static function getRelation($deptId)
    {
        $res = DeptModel::getById($deptId);
        return empty($res['relation']) ? null : $res['relation'];
    }

    /**
     * 添加或修改用户信息
     * @param $params
     * @return mixed
     */
    public static function insertOrUpdateDept($params)
    {
        $deptId = $params['id'] ?? null;

        $update = [
            'dept_name' => $params['dept_name'],
            'parent_id' => $params['parent_id'] ?? 0,
            'status' => $params['status'] ?? 1
        ];

        $parentId = $params['parent_id'] ?? null;
        if (empty($deptId)) {
            $update['create_time'] = time();

            $deptId = DeptModel::insertDept($update);

            //需回写relation
            if ($deptId) {
                $relation = self::getRelation($parentId) . $deptId . DeptModel::RELATION_SEPRATOR;
                DeptModel::updateDept($deptId, ['relation' => $relation]);
            }
            return $deptId;
        }

        $update['relation'] = self::getRelation($parentId) . $deptId . DeptModel::RELATION_SEPRATOR;

        DeptModel::updateDept($deptId, $update);

        return $deptId;
    }

    /**
     * 获取节点信息
     * @param $deptId
     * @return mixed
     */
    public static function getInfo($deptId)
    {
        return DeptModel::getById($deptId);
    }

    /**
     * 判断是否叶子节点
     * @param $deptId
     * @return bool
     */
    public static function isLeaf($deptId)
    {
        $isLeaf = true;
        if (DeptModel::getDeptInfo('id', [DeptModel::$table . '.parent_id' => $deptId])) {
            $isLeaf = false;
        }
        return $isLeaf;

    }

}