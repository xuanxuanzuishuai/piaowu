<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/6/28
 * Time: 下午4:24
 */

namespace App\Services;

use App\Models\GroupPrivilegeModel;
use App\Models\PrivilegeModel;
use App\Models\RoleModel;
use App\Models\EmployeeModel;

class EmployeePrivilegeService
{
    public static function getEmployeePIds($employee)
    {
        $pIds = [];
        $role = RoleModel::getById($employee['role_id']);

        if (!empty($role['group_ids'])) {
            $groupIds = explode(",", $role['group_ids']);
            foreach ($groupIds as $groupId) {
                $gps = GroupPrivilegeModel::getGroupPrivileges($groupId);
                foreach ($gps as $gp) {
                    $pIds[$gp['privilege_id']] = 1;
                }
            }
        }
        return array_keys($pIds);
    }

    /**
     * @param $privilege
     * @param $pIds
     * @param $uri
     * @param $method
     * @return bool
     */
    public static function hasPermission($privilege, $pIds, $uri, $method)
    {
        if (trim($privilege['uri']) == $uri && strtolower($privilege['method']) == strtolower($method) && in_array($privilege['id'], $pIds))
            return true;
        return false;
    }

    /**
     * 根据权限英文名查询用户是否有此权限
     * @param $employeeId
     * @param $privilege_en_name
     * @return bool
     */
    public static function hasPermissionByName($employeeId, $privilege_en_name){
        $privilege = PrivilegeModel::getByUniqEnName($privilege_en_name);
        $employee = EmployeeModel::getById($employeeId);
        $pIds = self::getEmployeePIds($employee);
        return in_array($privilege['id'], $pIds);
    }

    /**
     * 是否超级管理员
     * @param $employee
     * @return bool
     */
    public static function checkIsSuperAdmin($employee)
    {
        if (isset($employee['role_id']) && $employee['role_id'] == RoleModel::$superAdmin) {
            return true;
        }
        return false;
    }

    /**
     * 检测当前角色拥有的数据权限
     * @param $employee
     * @return bool
     */
    public static function checkEmployeeDataPermission($employee)
    {
        //超级管理员拥有所有权限,其他角色拥有查看自己创建的数据的权限
        if (self::checkIsSuperAdmin($employee)) {
            $onlyReadSelf = false;
        } else {
            $onlyReadSelf = true;
        }
        return $onlyReadSelf;
    }
}