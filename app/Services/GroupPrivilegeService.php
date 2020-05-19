<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2018/7/9
 * Time: 下午4:08
 */

namespace App\Services;

use App\Models\GroupModel;
use App\Models\GroupPrivilegeModel;
use App\Models\PrivilegeModel;

class GroupPrivilegeService
{
    /**
     * 添加或更新权限组
     * @param $groupId
     * @param $params
     * @return mixed
     */
    public static function insertOrUpdateGroup($groupId, $params)
    {
        $group = GroupModel::getById($groupId);
        $update = [
            'name' => $params['name'],
            'desc' => $params['desc'] ?? ''
        ];

        if (empty($group)) {
            $update['created_time'] = time();
            return GroupModel::insertGroup($update);
        }

        GroupModel::updateGroup($groupId, $update);
        return $groupId;
    }

    /**
     * 添加或更新权限
     * @param $id
     * @param $params
     * @return mixed
     */
    public static function insertOrUpdatePrivilege($id, $params)
    {
        $privilege = PrivilegeModel::getById($id);
        $update = [
            'name' => $params['name'],
            'uri' => $params['uri'],
            'method' => $params['method'],
            'unique_en_name' => $params['unique_en_name'],
        ];

        $update['parent_id'] = $params['parent_id'] ?? 0;
        $update['is_menu'] = $params['is_menu'] ?? PrivilegeModel::NOT_MENU;
        $update['menu_name'] = $params['menu_name'] ?? "";
        $update['status'] = $params['status'] ?? 1;

        if (empty($privilege)) {
            $update['created_time'] = time();
            return PrivilegeModel::insertPrivilege($update);
        }

        PrivilegeModel::updatePrivilege($id, $update);
        return $id;
    }

    /**
     * 获取权限组的权限列表
     * @param $groupId
     * @return mixed
     */
    public static function getGroupPrivileges($groupId)
    {
        return GroupPrivilegeModel::getGroupPrivileges($groupId);
    }
}