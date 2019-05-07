<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2018/7/9
 * Time: 下午2:11
 */

namespace App\Services;

use App\Libs\Constants;
use App\Models\OrganizationModel;
use App\Models\RoleModel;

class RoleService
{
    /**
     * 添加或更新用户角色
     * @param $roleId
     * @param $params
     * @return mixed
     */
    public static function insertOrUpdateRole($roleId, $params)
    {
        $role = RoleModel::getById($roleId);
        $update = [
            'name' => $params['name'],
            'desc' => $params['desc'] ?? '',
            'group_ids' => implode(',', $params['group_ids'])
        ];

        if (empty($role)) {
            $update['created_time'] = time();
            return RoleModel::insertRole($update);
        }

        RoleModel::updateRole($roleId, $update);
        return $roleId;
    }

    public static function getById($id) {
        return RoleModel::getById($id);
    }

    public static function getOrgTypeByOrgId($orgId)
    {
        if($orgId == OrganizationModel::ORG_ID_INTERNAL) {
            return RoleModel::ORG_TYPE_INTERNAL;
        } else {
            $ids = DictService::getKeyValue(Constants::DICT_TYPE_DIRECT_ORG_IDS, 1);
            $list = explode(',', $ids);
            if(in_array($orgId, $list)) {
                return RoleModel::ORG_TYPE_DIRECT;
            } else {
                return RoleModel::ORG_TYPE_EXTERNAL;
            }
        }
    }
}