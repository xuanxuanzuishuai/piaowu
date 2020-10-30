<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/6/26
 * Time: 上午11:34
 */

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Dict;
use App\Libs\DictConstants;
use App\Libs\DingDing;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\JWTUtils;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\EmployeeModel;
use App\Models\EmployeeSeatModel;
use App\Models\RoleModel;


class EmployeeService
{

    const ONE_MONTH_TIMESTAMP = 2592000; // 一个月
    const PWD_EXPIRE_DAYS = 30;

    public static function checkUcToken($token){

        // 用户中心验证Token及权限
        list($appId, $appSecret) = DictConstants::get(DictConstants::USER_CENTER, ['app_id_dss', 'app_secret_dss']);
        $uc = new UserCenter($appId, $appSecret);
        $ucData = $uc->CheckToken($token);
        if (empty($ucData['user'])){
            return Valid::addErrors([], 'xyz_token', 'no_privilege');
        }

        SimpleLogger::info(__FILE__ . ':' . __LINE__, [$ucData]);
        $userInfo = EmployeeModel::getByUuid($ucData['user']['uuid']);

        $expires = $ucData['expires'] - time();
        EmployeeModel::setEmployeeCache($userInfo, $token,  $expires);
        // 更新用户上次登录时间
        EmployeeModel::updateEmployee($userInfo['id'], ['last_login_time' => time()]);

        return array($ucData['token'], $userInfo, $ucData['expires']);
    }

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

        list($issuer, $audience, $expire, $signerKey, $tokenTypeUser) = DictService::getKeyValuesByArray(Constants::DICT_TYPE_SYSTEM_ENV,
            [
                Constants::DICT_KEY_JWT_ISSUER,
                Constants::DICT_KEY_JWT_AUDIENCE,
                Constants::DICT_KEY_JWT_EXPIRE,
                Constants::DICT_KEY_JWT_SIGNER_KEY,
                Constants::DICT_KEY_TOKEN_TYPE_USER
            ]);
        $jwtUtils = new JWTUtils($issuer, $audience, $expire, $signerKey);
        $token = $jwtUtils->getToken($tokenTypeUser, $employee['id'], $employee['name']);

        EmployeeModel::setEmployeeCache($employee, $token, $expire);
        // 更新用户上次登录时间
        EmployeeModel::updateEmployee($employee['id'], ['last_login_time' => time()]);

        return array($token, $employee);
    }

    public static function logout($token)
    {
        return EmployeeModel::delEmployeeToken($token);
    }

    /**
     * 修改密码
     * @param $userId
     * @param string $newPassword 后台修改不设置、不检查
     * @param string $oldPassword 后台修改不设置、不检查， 自己修改检查旧密码
     * @return array
     */
    public static function changePassword($userId, $newPassword, $oldPassword = ""){
        $employee = EmployeeModel::getById($userId);
        list($appId, $appSecret) = DictConstants::get(DictConstants::USER_CENTER, ['app_id_dss', 'app_secret_dss']);
        $userCenter = new UserCenter($appId, $appSecret);
        $authResult = $userCenter->changePassword($employee['uuid'], $newPassword, $oldPassword);
        if (empty($authResult['code'])){
            EmployeeModel::updateUserPassWord($userId, $newPassword);
        }
        return $authResult;
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
        $where['ORDER'] = [EmployeeModel::$table . '.status' => 'ASC', EmployeeModel::$table . '.created_time' => 'DESC'];

        /** 以下是用户的搜索条件 */
        // 登录名
        if (!empty($params['login_name'])) {
            $where['AND'][EmployeeModel::$table . '.login_name[~]'] = Util::sqlLike($params['login_name']);
        }
        // 角色
        if (!empty($params['role_id'])) {
            $where['AND'][EmployeeModel::$table . '.role_id'] = $params['role_id'];
        }
        // 状态
        if (isset($params['status'])) {
            $where['AND'][EmployeeModel::$table . '.status'] = $params['status'];
        }
        // 部门
        if (isset($params['dept_id'])) {
            $where['AND'][EmployeeModel::$table . '.dept_id'] = $params['dept_id'];
        }

        $totalCount = EmployeeModel::getEmployeeCount($where);
        if ($totalCount == 0) {
            return [[], 0];
        }
        $users = EmployeeModel::getEmployees($page, $count, $where);

        foreach ($users as $key => $user) {
            $users[$key]['status'] = Dict::normalOrInvalidStr($user['status']);
            $users[$key]['is_leader'] =  Dict::isOrNotStr($user['is_leader']);
        }
        return [$users, $totalCount];
    }

    /**
     * @param $userId
     * @return array
     * @throws RunTimeException
     * 获取用户详情
     */
    public static function getEmployeeDetail($userId)
    {
        $user = EmployeeModel::getEmployeeById($userId);
        $user['thumb'] = empty($user['wx_thumb']) ? '' : AliOSS::signUrls($user['wx_thumb']);
        $user['qr'] = empty($user['wx_qr']) ? '' : AliOSS::signUrls($user['wx_qr']);
        $roles = RoleModel::getRoles();
        //获取用户坐席数据
        list($seats, $inuseSeatType) = self::getEmployeeSeat($userId);
        $user['seats'] = $seats;
        $user['inuse_seat_type'] = $inuseSeatType;
        //绑定钉钉信息
        $dingDingMobileInfo = (new DingDing())->getMobileByUuid(['uuid' => $user['uuid']]);
        $user['ding_ding'] = ['mobile' => $dingDingMobileInfo['mobile'] ?? ''];
        return [$user, $roles];
    }

    /**
     * 获取用户坐席数据
     * @param $userId
     * @return array
     */
    public static function getEmployeeSeat($userId)
    {
        $seats = EmployeeSeatService::getUserSeats($userId);
        return self::formatUserSeat($seats);
    }

    /**
     * 格式化用户坐席数据
     * @param $seats
     * @return array
     */
    public static function formatUserSeat($seats)
    {
        $data = [];
        $inuseSeatType = '';
        if(empty($seats)){
            return [$data, $inuseSeatType];
        }
        foreach($seats as $seat){
            if($seat['status'] == EmployeeSeatModel::ON_USE){
                $inuseSeatType = $seat['seat_type'];
            }
            if($seat['seat_type'] == EmployeeSeatModel::SEAT_RONGLIAN){
                $data['ronglian'][] = $seat;
            }else{
                $data['tianrun'][] = $seat;
            }
        }
        return [$data, $inuseSeatType];
    }

    /**
     * 添加或修改用户信息
     * @param $params
     * @return mixed
     */
    public static function insertOrUpdateEmployee($params)
    {
        $userId = $params['id'] ?? '';
        $params['mobile'] = !empty($params['mobile']) ? trim($params['mobile']) : '';
        $update = [
            'login_name' => $params['login_name'],
            'name'       => $params['name'],
            'role_id'    => $params['role_id'],
            'mobile'     => $params['mobile'] ?? '',
            'status'     => $params['status'] ?? EmployeeModel::STATUS_NORMAL,
            'is_leader'  => $params['is_leader'] ?? 0,
            'teacher_id' => $params['teacher_id'] ?? null,
            'org_id'     => $params['org_id'] ?? 0,
            'dept_id'    => $params['dept_id'] ?? 0,
            'email'      => $params['email'] ?? '',
        ];

        /**
         * 用户中心调用获取UUID
         */
        $uuid = '';
        $pwd = $params['pwd'] ?? '';
        if (!empty($userId)){
            $employee = EmployeeModel::getById($userId);
            $uuid = $employee['uuid'];
            $pwd = "";
        }elseif(!empty($params['uuid'])){
            $uuid = $params['uuid'];
        }

        list($appId, $appSecret) = DictConstants::get(DictConstants::USER_CENTER, ['app_id_dss', 'app_secret_dss']);
        $userCenter = new UserCenter($appId, $appSecret);

        if(empty($uuid)){
            //添加一个employee时，为了防止user center返回冲突的错误，先将login name置为空，拿手机号获取一个uuid
            //然后再次调用接口，传入uuid和用户名，修改信息

            //获取uuid
            $authResult = $userCenter->employeeAuthorization('', '', $update['name'], $update['mobile'],
                $params['status'] == EmployeeModel::STATUS_NORMAL,'');
            if (empty($authResult["uuid"])) {
                return Valid::addErrors([], "login_name", "uc_add_employee_error");
            }

            //修改登录名
            $authResult = $userCenter->employeeAuthorization($update['login_name'], $pwd, $update['name'], $update['mobile'],
                $params['status'] == EmployeeModel::STATUS_NORMAL, $authResult['uuid']);
            if (empty($authResult["uuid"])) {
                return Valid::addErrors([], "login_name", "uc_add_employee_error");
            }

            //修改密码
            $changeResult = $userCenter->changePassword($authResult['uuid'], $pwd, null);
            if($changeResult['code'] != UserCenter::RSP_CODE_SUCCESS) {
                return $changeResult;
            }
        }else{
            $authResult = $userCenter->employeeAuthorization($update['login_name'], '', $update['name'], $update['mobile'],
                $params['status'] == EmployeeModel::STATUS_NORMAL, $uuid);
            if (empty($authResult["uuid"])) {
                return Valid::addErrors([], "login_name", "uc_add_employee_error");
            }
        }

        $update['uuid'] = $authResult['uuid'];

        if (empty($userId)) {
            $now = time();

            $update['created_time'] = $now;
            $update['last_update_pwd_time'] = $now;
            // 初始密码
            $update['pwd'] = md5($pwd);
            $userId = EmployeeModel::insertEmployee($update);

            if (empty($userId)) {
                return Valid::addErrors([], 'login_name', 'login_name_is_exist');
            }

            return $userId;
        }

        $affectRows = EmployeeModel::updateRecord($userId, $update, false);
        if(!is_numeric($affectRows)) {
            return Valid::addErrors([], 'employee', 'update_fail');
        }

        return $userId;
    }

    /**
     * 获取雇员列表数据
     * @param $roleId
     * @return array
     */
    public static function getEmployeeListWithRole($roleId)
    {
        $employees = EmployeeModel::getEmployeeWithRole($roleId);
        return $employees;
    }


    public static function getById($employeeId)
    {
        return EmployeeModel::getById($employeeId);
    }

    public static function getNameMap($employeeIds)
    {
        if (empty($employeeIds)) {
            return [];
        }
        return EmployeeModel::getRecords(['id' => $employeeIds], ['id', 'name'], false);
    }

    /**
     * 是否助教角色
     * @param $employeeId
     * @return bool
     */
    public static function isAssistantRole($employeeId)
    {
        if (empty($employeeId)) {
            return false;
        }
        $assistantRoleId = DictService::getKeyValue(Constants::DICT_TYPE_ROLE_ID, Constants::DICT_KEY_CODE_ASSISTANT);
        if ($assistantRoleId == $employeeId) {
            return true;
        }
        return false;
    }

    /**
     * 是否课管角色
     * @param $employeeId
     * @return bool
     */
    public static function isCourseManagerRole($employeeId)
    {
        if (empty($employeeId)) {
            return false;
        }
        $assistantRoleId = DictService::getKeyValue(Constants::DICT_TYPE_ROLE_ID, Constants::DICT_KEY_CODE_COURSE_MANAGE_ROLE_ID_CODE);
        if ($assistantRoleId == $employeeId) {
            return true;
        }
        return false;
    }

    public static function externalInformation($params)
    {
        $employeeData = EmployeeModel::getRecord(['id' => $params['id']]);
        if (empty($employeeData)) {
            throw new RunTimeException(['employee_not_exist']);
        }

        $updateResult = EmployeeModel::updateEmployee($params['id'], ['wx_nick' => $params['wx_nick'], 'wx_thumb' => $params['wx_thumb'], 'wx_qr' => $params['wx_qr']]);

        if (empty($updateResult)) {
            throw new RunTimeException(['update_failure']);
        }
        return $updateResult;
    }

    /**
     * 通过部门id获取本部门以及子部门的助教成员列表
     * @param $deptId
     * @return array
     */
    public static function getDeptAssistantMembers($deptId)
    {
        $members = StudentService::getDeptPrivilege($deptId);
        $list = [];
        if (empty($members['assistant_id'])) {
            return $list;
        }
        $list = EmployeeModel::getRecords(['id' => $members['assistant_id'], 'status' => EmployeeModel::STATUS_NORMAL], ['name', 'id'], false);
        return $list;
    }


    /**
     * 获取用户对外信息
     * @param $id
     * @return array|mixed
     */
    public static function getExternalInformation($id)
    {
        $employeeData = EmployeeModel::getRecord(['id' => $id], ['wx_nick', 'wx_thumb', 'wx_qr', 'uuid']);
        return $employeeData ?? [];
    }
    /**
     * 通过部门id获取本部门以及子部门的课管成员列表
     * @param $deptId
     * @return array
     */
    public static function getDeptCourseManageMembers($deptId)
    {
        $members = StudentService::getDeptPrivilege($deptId);
        $list = [];
        if (empty($members['course_manage_id'])) {
            return $list;
        }
        $list = EmployeeModel::getCourseManageStudentCount(implode(',',$members['course_manage_id']));
        return $list;
    }
}