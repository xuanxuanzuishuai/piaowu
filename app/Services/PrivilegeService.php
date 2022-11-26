<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2018/8/30
 * Time: 下午7:27
 */

namespace App\Services;


use App\Libs\Dict;
use App\Libs\Util;
use App\Models\PrivilegeModel;

class PrivilegeService
{
    /**
     * 获取权限列表
     * @param int $page
     * @param int $count
     * @param null $name
     * @param null $uri
     * @param null $status
     * @return array
     */
    public static function getPrivilegesService($page = 0, $count = 0, $name = null, $uri = null, $status = null)
    {
        $where = [];
        if (!empty($name)) {
            $where['AND'][PrivilegeModel::$table . '.name[~]'] = Util::sqlLike($name);
        }
        if (!empty($uri)) {
            $where['AND'][PrivilegeModel::$table . '.uri[~]'] = Util::sqlLike($uri);
        }
        if (!is_null($status)) {
            $where['AND'][PrivilegeModel::$table . '.status'] = $status;
        }

        $totalCount = PrivilegeModel::getPrivilegeCount($where);
        if ($totalCount == 0) {
            return [[], 0];
        }

        $where['ORDER'] = [PrivilegeModel::$table . '.is_menu' => 'DESC'];

        if ($page > 0 && $count > 0) {
            $where['LIMIT'] = [($page - 1) * $count, $count];
        }

        $privileges = PrivilegeModel::selectPrivileges($where);

        foreach ($privileges as $key => $privilege) {
            $privileges[$key]['menu'] = Dict::isOrNotStr($privilege['is_menu']);
            $privileges[$key]['pstatus'] = Dict::isOrNotStr($privilege['status']);
        }
        return [$privileges, $totalCount];
    }

    /**
     * 获取一级菜单
     * @return array
     */
    public static function getMenuService()
    {
        $menus = PrivilegeModel::getFirstMenu();
        return $menus;
    }

    /**
     * 获取员工的主菜单
     * @param $employeeId
     * @return array
     */
    public static function getEmployeeMenuService($employeeId)
    {
        $privilegeIds = [];
        $employee = EmployeeService::getById($employeeId);

        $role = RoleService::getById($employee['role_id']);
        if (!empty($role['group_ids'])) {
            $groupIds = explode(",", $role['group_ids']);
            foreach ($groupIds as $groupId) {
                $gps = GroupPrivilegeService::getGroupPrivileges($groupId);
                foreach ($gps as $gp) {
                    $privilegeIds[] = $gp['privilege_id'];
                }
            }
        }
        if (empty($privilegeIds)) {
            return [];
        }
        $menus = PrivilegeModel::getEmployeeMenu($privilegeIds);
        return $menus;
    }
}