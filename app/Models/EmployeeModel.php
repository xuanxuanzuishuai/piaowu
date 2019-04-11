<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/6/27
 * Time: 下午1:38
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\RedisDB;
use App\Services\EmployeeService;
use Medoo\Medoo;

class EmployeeModel extends Model
{
    private static $cacheKeyTokenPri = "token_";
    public static $table = "employee";
    public static $redisExpire = 3600 * 8;
    public static $redisDB;
    public static $initPwd = "xiaoyezi123";


    const STATUS_NORMAL = 1;
    const STATUS_DEL = 0;

    const SUPER_ADMIN_ROLE_ID = -1; //强制规定项目超级管理员 admin_role_id为 -1，不可改变

    const SYSTEM_EMPLOYEE_ID = 10000;
    const SYSTEM_EMPLOYEE_NAME = '系统';

    /**
     * @param $loginName
     * @return mixed
     */
    public static function getEmployeeByLoginName($loginName)
    {
        $user = MysqlDB::getDB()->get(self::$table, [
            self::$table . '.id',
            self::$table . '.name',
            self::$table . '.role_id',
            self::$table . '.status',
            self::$table . '.login_name',
            self::$table . '.pwd',
            self::$table . '.is_leader',
            self::$table . '.last_update_pwd_time',
        ], [
            'AND' => [
                'login_name' => $loginName,
                self::$table . '.status' => self::STATUS_NORMAL
            ]
        ]);
        return $user;
    }

    /**
     * @param $employee
     * @param $token
     * @param $expires
     * @return bool
     */
    public static function setEmployeeCache($employee, $token, $expires)
    {
        $redis = RedisDB::getConn(self::$redisDB);
        $cacheKey = self::createCacheKey($token, self::$cacheKeyTokenPri);
        $redis->setex($cacheKey, $expires, $employee['id']);
        return true;
    }

    /**
     * 操作延长过期时间
     * @param $token
     * @return bool
     */
    public static function refreshEmployeeCache($token){
        $redis = RedisDB::getConn(self::$redisDB);
        $cacheKey = self::createCacheKey($token, self::$cacheKeyTokenPri);
        $redis->expire($cacheKey, self::$redisExpire);
        return true;
    }

    /**
     * @param $token
     * @return string
     */
    public static function getEmployeeToken($token)
    {
        $redis = RedisDB::getConn(self::$redisDB);
        $cacheKey = self::createCacheKey($token, self::$cacheKeyTokenPri);
        return $redis->get($cacheKey);
    }

    /**
     * @param $token
     * @return int
     */
    public static function delEmployeeToken($token)
    {
        $redis = RedisDB::getConn(self::$redisDB);
        return $redis->del(self::createCacheKey($token, self::$cacheKeyTokenPri));
    }

    /**
     * 获取所有员工
     * @param int $page
     * @param int $count
     * @param $where
     * @return mixed
     */
    public static function getEmployees($page = 0, $count = 0, $where)
    {
        $db = MysqlDB::getDB();
        if ($page > 0 && $count > 0) {
            $where['LIMIT'] = [($page - 1) * $count, $count];
        }

        $users = $db->select(self::$table, [
            '[>]' . RoleModel::$table => ['role_id' => 'id']
        ], [
            self::$table . '.id',
            self::$table . '.name',
            self::$table . '.role_id',
            self::$table . '.mobile',
            self::$table . '.login_name',
            self::$table . '.status',
            self::$table . '.dept_id',
            self::$table . '.is_leader',
            self::$table . '.last_login_time',
            RoleModel::$table . '.name(role_name)'
        ], $where);
        return $users ?: [];
    }


    /**
     * 获取员工总数
     * @param $where
     * @return number
     */
    public static function getEmployeeCount($where)
    {
        return MysqlDB::getDB()->count(self::$table, '*', $where);
    }

    /**
     * 添加员工
     * @param $insert
     * @return mixed
     */
    public static function insertEmployee($insert)
    {
        $db = MysqlDB::getDB();
        return $db->insertGetID(self::$table, $insert);
    }

    /**
     * 更新员工信息
     * @param $id
     * @param $update
     * @return bool
     */
    public static function updateEmployee($id, $update)
    {
        $result = self::updateRecord($id, $update);
        return ($result && $result > 0);
    }

    /**
     * 更新员工密码
     * @param $id
     * @param $pwd
     * @return mixed
     */
    public static function updateUserPassWord($id, $pwd)
    {
        $result = MysqlDB::getDB()->updateGetCount(self::$table, [
            'pwd' => md5($pwd),
            'last_update_pwd_time' => time()
        ], ['id' => $id]);
        if ($result && $result > 0) {
            self::delCache($id);
            return true;
        }
        return false;
    }

    /**
     * 获取指定雇员
     * @param $studentCaIds
     * @return array
     */
    public static function getEmployeeWithIds($studentCaIds)
    {
        return MysqlDB::getDB()->select(self::$table, '*', [
            'status' => self::STATUS_NORMAL,
            'id' => $studentCaIds
        ]);
    }

    /**
     * 获取指定角色雇员
     * @param $roleId
     * @return array
     */
    public static function getEmployeeWithRole($roleId)
    {
        return MysqlDB::getDB()->select(self::$table, ['id', 'name'], [
            'role_id' => $roleId,
            'status' => self::STATUS_NORMAL
        ]);
    }

    /**
     * 查询离密码过期剩五天的用户
     * @param $notExpireRole
     * @return array
     */
    public static function selectEmployeePwdExpire($notExpireRole)
    {
        return MysqlDB::getDB()->select(self::$table, [
            self::$table . '.id(employee_id)',
            'expire_days' => Medoo::raw("floor((last_update_pwd_time + " . EmployeeService::ONE_MONTH_TIMESTAMP . " - unix_timestamp()) / 86400)")
        ], [
            'role_id[!]' => $notExpireRole,
            self::$table . '.status' => self::STATUS_NORMAL,
            'last_update_pwd_time[<=]' => strtotime(date("Y-m-d") . "-25 day"),
            'last_update_pwd_time[>]' => strtotime(date("Y-m-d") . "-30 day")
        ]);
    }

    /**
     * 获取员工详细信息
     * @param $id
     * @return mixed
     */
    public static function getEmployeeById($id)
    {
        $user = MysqlDB::getDB()->get(self::$table, [
            '[>]' . RoleModel::$table => ['role_id' => 'id'],
            '[>]' . EmployeeSeatModel::$table => ['id' => 'employee_id'],
        ], [
            self::$table . '.id',
            self::$table . '.name',
            self::$table . '.role_id',
            self::$table . '.mobile',
            self::$table . '.login_name',
            self::$table . '.status',
            self::$table . '.dept_id',
            self::$table . '.is_leader',
            self::$table . '.last_login_time',
            RoleModel::$table . '.name(role_name)',
            EmployeeSeatModel::$table . ".seat_type",
            EmployeeSeatModel::$table . ".seat_id",
            EmployeeSeatModel::$table . '.seat_tel',
        ], [self::$table . '.id' => $id]);
        return $user;
    }

    /**
     * 根据UUID获取用户信息
     * @param $uuid
     * @return mixed
     */
    public static function getByUuid($uuid)
    {
        $user = MysqlDB::getDB()->get(self::$table, [
                '[>]' . DeptModel::$table => ['dept_id' => 'id'],
                '[>]' . EmployeeSeatModel::$table => ['id' => 'employee_id']
            ],[
            self::$table . '.id',
            self::$table . '.name',
            self::$table . '.role_id',
            self::$table . '.status',
            self::$table . '.login_name',
            self::$table . '.pwd',
            self::$table . '.is_leader',
            self::$table . '.last_update_pwd_time',
            EmployeeSeatModel::$table . '.seat_type',
            EmployeeSeatModel::$table . '.seat_id',
            DeptModel::$table . '.dept_name'
        ], [
            'AND' => [
                self::$table . '.uuid' => $uuid,
                self::$table . '.status' => self::STATUS_NORMAL
            ]
        ]);
        return $user;
    }
}