<?php

namespace App\Services;

use App\Libs\Util;
use App\Libs\Valid;
use App\Models\EmployeeModel;
use App\Libs\Exceptions\RunTimeException;
use App\Models\RegionBelongManageModel;
use App\Models\RoleModel;

class EmployeeService
{

    /**
     * @param $employeeName
     * @param $password
     * @return array
     */
    public static function login($employeeName, $password)
    {
        $employee = EmployeeModel::getEmployeeByLoginName($employeeName);

        if (empty($employee)) {
            return Valid::addErrors([], 'login_name', 'employee_not_exists_or_not_active');
        }
        // 密码
        $pwd = md5($password);
        if ($employee['pwd'] != $pwd) {
            return Valid::addErrors([], 'pwd', 'employee_pwd_error');
        }
        unset($employee['pwd']);

        $token = EmployeeTokenService::generateToken($employee['id']);

        return array($token, $employee);
    }

    /**
     * 获取用户列表
     * @param $page
     * @param $count
     * @param $params
     * @return mixed
     */
    public static function getEmployeeService($page, $count, $params)
    {
        $where['ORDER'] = [EmployeeModel::$table . '.id' => 'DESC'];


        // 登录名
        if (!empty($params['login_name'])) {
            $where['AND'][EmployeeModel::$table . '.login_name[~]'] = Util::sqlLike($params['login_name']);
        }

        // 角色
        if (!empty($params['role_id'])) {
            $where['AND'][EmployeeModel::$table . '.role_id'] = $params['role_id'];
        }



        $totalCount = EmployeeModel::getEmployeeCount($where);


        if ($totalCount == 0) {
            return [[], 0];
        }

        $users = EmployeeModel::getEmployees($page, $count, $where);

        foreach ($users as $key => $user) {
            $users[$key]['status_msg'] = EmployeeModel::STATUS_MSG[$user['status']];

        }

        return [$users, $totalCount];
    }


    /**
     * 新增编辑员工
     * @param $employeeId
     * @param $params
     * @throws RunTimeException
     */
    public static function addEmployee($employeeId, $params)
    {
        $employeeInfo = EmployeeModel::getRecord(['id' => $employeeId]);
        if (empty($employeeInfo)) {
            throw new RuntimeException(['not_found_employee']);
        }

        //仅管理员和大区经理可以处理账号
        if (!in_array($employeeInfo['role_id'], [RoleModel::SUPER_ADMIN, RoleModel::REGION_MANAGE])) {
            throw new RuntimeException(['only_admin_region_manage']);
        }

        //不可处理更高角色账号
        if ($params['role_id'] > $employeeInfo['role_id']) {
            throw new RuntimeException(['not_allow_deal_more_role_account']);
        }

        $where = [
            'login_name' => $params['login_name']
        ];

        if (!empty($params['employee_id'])) {
            $where['id[!]'] = $params['employee_id'];
        }

        $res = EmployeeModel::getRecord($where);

        if (!empty($res)) {
            throw new RuntimeException(['login_name_has_exist']);
        }


        if (empty($params['pwd']) && empty($params['employee_id'])) {
            throw new RuntimeException(['pwd_is_required']);
        }


        if (empty($params['employee_id'])) {
            EmployeeModel::insertRecord(
                [
                    'name' => $params['name'],
                    'role_id' => $params['role_id'],
                    'login_name' => $params['login_name'],
                    'mobile' => $params['mobile'],
                    'pwd' => md5($params['pwd']),
                    'create_time' => time(),
                    'update_time' => time(),
                    'operator_id' => $employeeId,
                    'status' => $params['status']
                ]
            );
        } else {
            EmployeeModel::updateRecord($params['employee_id'],
                [
                    'name' => $params['name'],
                    'role_id' => $params['role_id'],
                    'login_name' => $params['login_name'],
                    'mobile' => $params['mobile'],
                    'update_time' => time(),
                    'operator_id' => $employeeId,
                    'status' => $params['status']
                ]);
        }

        EmployeeTokenService::expireUserToken($params['employee_id']);

    }

    public static function updatePwd($employeeId, $params)
    {
        $employeeInfo = EmployeeModel::getRecord(['id' => $employeeId]);
        if (empty($employeeInfo)) {
            throw new RuntimeException(['not_found_employee']);
        }


        if ($employeeInfo['id'] != $params['employee_id']) {
            throw new RuntimeException(['only_self_change_other']);
        }

        EmployeeModel::updateRecord($params['employee_id'], [
            'pwd' => md5($params['pwd']),
            'operator_id' => $employeeId,
            'update_time' => time()
        ]);


        EmployeeTokenService::expireUserToken($params['employee_id']);

    }

    /**
     * 员工详情
     * @param $employeeId
     * @return array
     */
    public static function getEmployeeInfo($employeeId)
    {
        $info = EmployeeModel::getEmployeeById($employeeId);

        $relateRegion = [];

        if ($info['role_id'] == RoleModel::REGION_MANAGE) {
            $relateRegion = RegionBelongManageModel::getEmployeeRelateRegion($employeeId);
        }
        return [$info, $relateRegion];
    }


}