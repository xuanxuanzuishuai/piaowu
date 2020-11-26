<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2018/7/9
 * Time: 下午2:11
 */

namespace App\Services;

use App\Libs\Constants;
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
            $ids = DictService::getKeyValue(Constants::DICT_TYPE_DIRECT_ORG_IDS, 1);
            $list = explode(',', $ids);
            if(in_array($orgId, $list)) {
                return RoleModel::ORG_TYPE_DIRECT;
            } else {
                return RoleModel::ORG_TYPE_EXTERNAL;
            }
    }

    //根据orgId确定要查询角色的org_type
    //内部管理人员可以查询到所有角色
    //直营只能查询到直营角色和外部角色(除校长)
    //外部只能查询到外部角色（除校长）
    public static function selectRoleByOrg($orgId = null, $except = [])
    {
        if (empty($orgId)) {
            $records = RoleModel::getRoles();
        } else {
            $orgType = RoleService::getOrgTypeByOrgId($orgId);

            if($orgType == RoleModel::ORG_TYPE_DIRECT) {
                $records = RoleModel::selectByOrgType([RoleModel::ORG_TYPE_DIRECT, RoleModel::ORG_TYPE_EXTERNAL]);
            } else {
                $records = RoleModel::selectByOrgType($orgType);
            }

            $records = array_filter($records, function($ele) use ($except) {
                return !in_array($ele['id'], $except);
            });
        }

        return $records;
    }
}