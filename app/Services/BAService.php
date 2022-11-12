<?php

namespace App\Services;


use App\Libs\Exceptions\RunTimeException;
use App\Models\BAApplyModel;
use App\Models\BAListModel;
use App\Models\EmployeeModel;
use App\Models\RoleModel;

class BAService
{
    /**
     * 获取BA的申请列表
     * @param $employeeId
     * @return array|null
     */
    public static function getBAApplyList($employeeId, $parmas, $page, $count)
    {
        $employeeInfo = EmployeeModel::getRecord(['id' => $employeeId]);

        $list = [];
        if ($employeeInfo['role_id'] == RoleModel::BA_MANAGE) {
            list($list, $totalCount) = BAApplyModel::getBaManageApplyList($employeeId, $parmas, $page, $count);
        }

        if ($employeeInfo['role_id'] == RoleModel::REGION_MANAGE) {
            list($list, $totalCount) = BAApplyModel::getRegionManageApplyList($employeeId, $parmas, $page, $count);
        }

        if ($employeeInfo['role_id'] == RoleModel::SUPER_ADMIN) {
            list($list, $totalCount) = BAApplyModel::getSuperApplyList($parmas, $page, $count);
        }
         return [$list, $totalCount];
    }

    /**
     * 处理ba申请
     * @param $employeeId
     * @param $params
     * @throws RunTimeException
     */
    public static function updateApply($employeeId, $params)
    {
        $arr = explode(',', $params['ids']);
        $checkStatus = $params['check_status'];

        $allApplyInfo = BAApplyModel::getRecords(['id' => $arr]);
        $allApplyInfoStatus = array_column($allApplyInfo, 'check_status');

        //已经处理过的不可再次处理
        if (in_array(BAApplyModel::APPLY_PASS, $allApplyInfoStatus) || in_array(BAApplyModel::APPLY_REJECT, $allApplyInfoStatus)) {
            throw new RuntimeException(["not_allow_update_has_update_apply"]);
        }

        foreach ($allApplyInfo as $value) {
            //已经存在的工号，不可再次入表BA
            $res = BAListModel::getRecord(['job_number' => $value['job_number']]);
            if (empty($res)) {
                BAListModel::insertRecord(
                    [
                        'mobile' => $value['mobile'],
                        'name' => $value['name'],
                        'job_number' => $value['job_number'],
                        'shop_number' => $value['shop_number'],
                        'create_time' => $value['create_time'],
                        'shop_id' => $value['shop_id']
                    ]
                );
            }
        }

        BAApplyModel::batchUpdateRecord(['check_status' => $checkStatus, 'operator_employee' => $employeeId, 'update_time' => time()], ['id' => $arr]);
    }

    /**
     * BA的info
     * @param $baId
     * @return mixed
     */
    public static function getBaInfo($baId)
    {
        $baInfo = BAListModel::getBaInfo($baId)[0];
        return $baInfo;
    }

    /**
     * 获取已通过的BA
     * @param $employeeId
     * @return array|null
     */
    public static function getPassBa($employeeId, $page, $count)
    {
        $employeeInfo = EmployeeModel::getRecord(['id' => $employeeId]);


        $list = [];
        if ($employeeInfo['role_id'] == RoleModel::BA_MANAGE) {
            list($list, $totalCount) = BAListModel::getBaManageApplyList($employeeId, $page, $count);
        }

        if ($employeeInfo['role_id'] == RoleModel::REGION_MANAGE) {
            list($list, $totalCount) = BAListModel::getRegionManageApplyList($employeeId, $page, $count);
        }

        if ($employeeInfo['role_id'] == RoleModel::SUPER_ADMIN) {
            list($list, $totalCount) = BAListModel::getSuperApplyList($page, $count);
        }
        return [$list, $totalCount];
    }
}