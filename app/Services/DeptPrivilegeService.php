<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/6/28
 * Time: 6:53 PM
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Models\DeptModel;
use App\Models\DeptPrivilegeModel;

class DeptPrivilegeService
{
    public static function getByDept($deptId)
    {
        $privileges = DeptPrivilegeModel::getByDept($deptId);

        $dataTypeNames = DictConstants::getSet(DictConstants::DEPT_DATA_TYPE_NAME);
        $privilegeTypeNames = DictConstants::getSet(DictConstants::DEPT_PRIVILEGE_TYPE_NAME);
        foreach ($privileges as &$p) {
            $p['data_type_name'] = $dataTypeNames[$p['data_type']];
            $p['privilege_type_name'] = $privilegeTypeNames[$p['privilege_type']];
        }

        return $privileges;
    }

    /**
     * @param $params
     * @param $operator
     * @return int
     * @throws RunTimeException
     */
    public static function modify($params, $operator)
    {
        if (!isset($params['data_type']) || !in_array($params['data_type'], [DeptPrivilegeModel::DATA_TYPE_STUDENT])) {
            throw new RunTimeException(['dept_invalid_data_type']);
        }

        if (!isset($params['privilege_type']) || !in_array($params['privilege_type'], [
            DeptPrivilegeModel::PRIVILEGE_SELF,
            DeptPrivilegeModel::PRIVILEGE_SUBS,
            DeptPrivilegeModel::PRIVILEGE_ALL,
            DeptPrivilegeModel::PRIVILEGE_CUSTOM,
            ])) {
            throw new RunTimeException(['dept_invalid_privilege_type']);
        }

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
        if (!isset($params['data_type'])) {
            throw new RunTimeException(['dept_invalid_data_type']);
        }

        if (!isset($params['privilege_type'])) {
            throw new RunTimeException(['dept_invalid_privilege_type']);
        }

        $deptId = $params['dept_id'] ?? null;

        if (empty($deptId)) {
            throw new RunTimeException(['record_not_found']);
        }

        $dept = DeptModel::getById($deptId);
        if (empty($dept)) {
            throw new RunTimeException(['record_not_found']);
        }

        $deptPrivilege = DeptPrivilegeModel::getRecords([
            'dept_id' => $deptId,
            'data_type' => $params['data_type']
        ], 'dept_id');
        if (!empty($deptPrivilege)) {
            throw new RunTimeException(['record_exist']);
        }

        $insert = [
            'dept_id' => $deptId,
            'data_type' => $params['data_type'],
            'privilege_type' => $params['privilege_type'],
            'status' => Constants::STATUS_TRUE,
            'create_time' => time(),
            'update_time' => 0,
            'operator' => $operator,
        ];
        $id = DeptPrivilegeModel::insertRecord($insert);

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
        $privilege = DeptPrivilegeModel::getById($params['id']);
        if (empty($privilege)) {
            throw new RunTimeException(['record_not_found']);
        }

        $update = [];

        $privilegeType = $params['privilege_type'] ?? null;
        if (isset($privilegeType) && $privilegeType != $privilege['privilege_type']) {
            $update['privilege_type'] = $privilegeType;
        }

        $privilegeType = $params['status'] ?? null;
        if (isset($privilegeType) && $privilegeType != $privilege['status']) {
            $update['status'] = $privilegeType;
        }

        if (empty($update)) {
            throw new RunTimeException(['nothing_change']);
        }

        $update['update_time'] = time();
        $update['operator'] = $operator;

        $count = DeptPrivilegeModel::updateRecord($params['id'], $update);

        if (empty($count)) {
            throw new RunTimeException(['update_failure']);
        }

        return $count;
    }


}