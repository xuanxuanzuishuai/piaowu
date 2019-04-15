<?php
/**
 * Created by PhpStorm.
 * User: liliang
 * Date: 18/7/19
 * Time: 下午3:37
 */

namespace App\Services;

use App\Models\DeptDataModel;

class DeptDataService
{
    /**
     * 添加或修改部门数据权限
     * @param $params
     * @return null
     */
    public static function insertOrUpdateDeptData($params)
    {
        $update = [
            'dept_id' => $params['dept_id'],
            'data_type' => $params['type'],
            'dept_ids' => implode(',', $params['dept_ids'])
        ];

        $deptData = DeptDataModel::getDeptData($params['dept_id']);

        if (empty($deptData)) {
            $update['create_time'] = time();

            return DeptDataModel::insertDeptData($update);
        }
        $deptData = $deptData[0];
        DeptDataModel::updateDeptData($deptData['id'], $update);

        return $deptData['id'];
    }

    /**
     * 获取部门数据权限列表
     * @param $deptId
     * @param int $dataType
     * @return null
     */

    public static function getDeptDataIds($deptId, $dataType)
    {
        $info = DeptDataModel::getDeptDataWithType($deptId,  $dataType);

        $deptIds = isset($info[0]['dept_ids']) ? $info[0]['dept_ids'] : null;

        return $deptIds;
    }

    /**
     * 判断用户是否有权限
     * @param $privilege
     * @param $deptId
     * @return bool
     */
    public static function checkDataPrivilege($privilege, $deptId)
    {
        //超级权限
        if ($privilege['is_super']) {
            return true;
        }
        //self 权限则无部门数据权限
        if ($privilege['is_self']) {
            return false;
        }
        //是否在where中
        return in_array($deptId, $privilege['where']) ? true : false;

    }

    /**
     * 结构化用户权限
     * @param $user
     * @param int $dataType
     * @return array
     */
    public static function getUserPrivilege($user, $dataType = DeptDataModel::DATA_TYPE_DEFAULT)
    {
        $data = array();
        $data['is_super'] = 0; //是否超级权限
        $data['is_self'] = 0; //是否只有自己权限
        $data['dept_ids'] = 0; //部门权限 介于两者直接
        $data['where'] = [];
        $data['group'] = "";

        $deptIds = self::getDeptDataIds($user['dept_id'], $dataType);

        $data['dept_ids'] = $deptIds;
        //超级权限
        if ((int)$deptIds === DeptDataModel::SUPER_PRIVILEGE) {
            $data['is_super'] = 1;
            $data['group'] = DeptDataModel::DATA_GROUP_DEPT_ID;
            return $data;
        }
        //用户部门为叶子节点且数据权限为自己部门权限
        if ($deptIds == $user['dept_id'] && DeptService::isLeaf($user['dept_id'])) {

            //不是leader
            if (!$user['is_leader']) {
                $data['is_self'] = 1;
            } else {
                $data['group'] = DeptDataModel::DATA_GROUP_ID;
                $data['where'] = [$deptIds];
            }
            return $data;
        }
        //循环获取查询树 当有多个部门 返回多个部门的汇总数据 当只有一个部门 则 显示下级部门的集合
        $deptIdArr = explode(',', $deptIds);
        $deptIdArr = array_map('intval', $deptIdArr);

        foreach ($deptIdArr as $item) {
            $children = DeptService::getChild($item);
            $data['where'] = array_merge($data['where'], $children);
            $data['group'] = DeptDataModel::DATA_GROUP_DEPT_ID;
        }

        return $data;
    }
}