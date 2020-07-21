<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/6/22
 * Time: 3:29 PM
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\ListTree;
use App\Libs\Util;
use App\Models\DeptModel;
use App\Models\DeptPrivilegeModel;
use App\Models\EmployeeModel;

class DeptService
{
    /**
     * 组织架构关系树形列表
     * @return array
     */
    public static function tree()
    {
        $tree = DeptModel::getTree();

        return ['tree' => $tree];
    }

    /**
     * 列表
     * @param $params
     * @return array
     */
    public static function list($params)
    {
        $where = [];

        if (!empty($params['id'])) {
            $where['d.id'] = $params['id'];
        } elseif (!empty($params['name'])) {
            $where['d.name[~]'] = Util::sqlLike($params['name']);
        }

        if (isset($params['status'])) {
            $where['d.status'] = $params['status'];
        }

        if (isset($params['parent_id'])) {
            $where['d.parent_id'] = $params['parent_id'];
        }

        list($page, $count) = Util::formatPageCount($params);

        list($records, $totalCount) = DeptModel::list($where, $page, $count);

        return [$records, $totalCount];
    }

    /**
     * @param $params
     * @param $operator
     * @return int
     * @throws RunTimeException
     */
    public static function modify($params, $operator)
    {
        if (isset($params['id'])) {
            $ret = self::update($params, $operator);
        } else {
            $ret = self::new($params, $operator);
        }

        return $ret;
    }

    /**
     * @param $params
     * @param $operator
     * @return int
     * @throws RunTimeException
     */
    public static function new($params, $operator)
    {
        $now = time();

        if (!empty($params['parent_id'])) {
            $parent = DeptModel::getById($params['parent_id']);

            if (empty($parent)) {
                throw new RunTimeException(['parent_not_found']);
            }
        }

        $insert = [
            'name' => $params['name'],
            'parent_id' => $params['parent_id'] ?? 0,
            'status' => Constants::STATUS_TRUE,
            'create_time' => $now,
            'update_time' => 0,
            'operator' => $operator,
        ];

        $id = DeptModel::insertRecord($insert);

        if (empty($id)) {
            throw new RunTimeException(['insert_failure']);
        }

        return $id;
    }

    /**
     * @param $params
     * @param $operator
     * @return int
     * @throws RunTimeException
     */
    public static function update($params, $operator)
    {
        $dept = DeptModel::getById($params['id']);
        if (empty($dept)) {
            throw new RunTimeException(['record_not_found']);
        }

        $update = [];

        if (isset($params['parent_id']) && $params['parent_id'] != $dept['parent_id']) {
            if ($params['parent_id'] == $dept['id']) {
                throw new RunTimeException(['dept_invalid_parent_id']);
            }

            if ($params['parent_id'] == 0) {
                $update['parent_id'] = 0;

            } else {
                $parent = DeptModel::getById($params['parent_id']);
                if (empty($parent)) {
                    throw new RunTimeException(['record_not_found']);
                }

                $update['parent_id'] = $params['parent_id'];
            }
        }

        if (isset($params['status']) && $params['status'] != $dept['status']) {
            $update['status'] = $params['status'];
        }

        if (isset($params['name']) && $params['name'] != $dept['name']) {
            $update['name'] = $params['name'];
        }

        if (empty($update)) {
            throw new RunTimeException(['nothing_change']);
        }

        $update['update_time'] = time();
        $update['operator'] = $operator;

        $count = DeptModel::updateRecord($params['id'], $update);

        if (empty($count)) {
            throw new RunTimeException(['update_failure']);
        }

        return $count;
    }

    /**
     * 获取部门下属成员
     *
     * @param int $deptId 部门ID
     * @param null $dataType 数据类型，未选择数据类型默认获取本组内成员
     * @return array
     */
    public static function getMembers($deptId, $dataType = null)
    {
        if (empty($dataType)) {
            $privilegeType = DeptPrivilegeModel::PRIVILEGE_SELF;
        } else {
            $privilege = DeptPrivilegeModel::getRecord([
                'dept_id' => $deptId,
                'data_type' => $dataType,
                'status' => Constants::STATUS_TRUE
            ]);
            $privilegeType = $privilege['privilege_type'] ?? DeptPrivilegeModel::PRIVILEGE_SELF;
        }

        $depts = DeptModel::getList();
        $lt = new ListTree($depts);

        if ($privilegeType == DeptPrivilegeModel::PRIVILEGE_ALL) {

            $memberDeptIds = $lt->getChildren(0, true);

        } elseif ($privilegeType == DeptPrivilegeModel::PRIVILEGE_SUBS) {

            $memberDeptIds = $lt->getChildren($deptId, true);
            if ($deptId > 0) {
                $memberDeptIds[] = $deptId;
            }

        } elseif ($privilegeType == DeptPrivilegeModel::PRIVILEGE_SELF) {

            if ($deptId > 0) {
                $memberDeptIds[] = $deptId;
            }

        }

        if (empty($memberDeptIds)) {
            return [];
        }

        $members = EmployeeModel::getRecords(['dept_id' => $memberDeptIds]);
        return $members;
    }

    /**
     * 检测是否是下属部门
     *
     * @param $subDeptId
     * @param $deptId
     * @param $dataType
     * @return bool
     */
    public static function isSubDept($subDeptId, $deptId, $dataType)
    {
        $privilege = DeptPrivilegeModel::getRecord(['dept_id' => $deptId, 'data_type' => $dataType]);
        $privilegeType = $privilege['privilege_type'] ?? DeptPrivilegeModel::PRIVILEGE_SELF;

        if ($privilegeType == DeptPrivilegeModel::PRIVILEGE_ALL) {
            return true;
        }

        if ($privilegeType == DeptPrivilegeModel::PRIVILEGE_SUBS) {
            $depts = DeptModel::getList();
            $lt = new ListTree($depts);
            $isContains = $lt->contains($subDeptId, $deptId);

            return $isContains;
        }

        if ($privilegeType == DeptPrivilegeModel::PRIVILEGE_SELF) {
            return $subDeptId == $deptId;
        }

        return false;
    }

    /**
     * @param $employeeUuid
     * @return string
     * 通过末端部门得到所有的上级部门
     */
    public static function getSubAllParentDept($employeeUuid)
    {
        $deptId = EmployeeModel::getRecord(['uuid' => $employeeUuid], 'dept_id');
        $arr[] = $deptId;
        $i = 0;
        if (!empty($deptId)) {
            while (true) {
                //防止死循环
                if ($i > 50) {
                    break;
                }
                $parentId = DeptModel::getRecord(['id' => $deptId], 'parent_id');
                if ($parentId != '0') {
                    $arr[] = $parentId;
                } else {
                    break;
                }
                $i++;
                $deptId = $parentId;
            }
        }
        return implode(',', array_reverse($arr));
    }
}