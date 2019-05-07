<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/16
 * Time: 16:30
 */
namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\Util;
//use Intervention\Image\ImageManagerStatic as Image;

class OrganizationModel extends Model
{
    public static $table = "organization";

    const STATUS_NORMAL = 1; //正常
    const STATUS_STOP = 0; //停用

    const ORG_ID_INTERNAL = 0; //内部角色固定org_id
    const ORG_ID_DIRECT = 1; //直营角色固定org_id

    const PRINCIPAL_NORMAL = 21;
    const PRINCIPAL_SPECIAL = 17;

    /**
     * 查询机构列表
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function selectOrgList($page, $count, $params)
    {
        $limit = Util::limitation($page, $count);

        $s   = StudentModel::$table;
        $so  = StudentOrgModel::$table;
        $t   = TeacherModel::$table;
        $too = TeacherOrgModel::$table;
        $e   = EmployeeModel::$table;
        $o   = OrganizationModel::$table;
        $ro  = RoleModel::$table;

        $studentStatus    = StudentModel::STATUS_NORMAL;
        $teacherStatus    = TeacherModel::STATUS_NORMAL;
        $studentOrgStatus = StudentOrgModel::STATUS_NORMAL;
        $teacherOrgStatus = TeacherOrgModel::STATUS_NORMAL;

        $where = ' where 1=1 ';
        $map   = [];

        $db = MysqlDB::getDB();

        $records = $db->queryAll("select e2.name principal_name,e2.mobile principal_mobile,e2.login_name principal_login_name,
        o.*,e.name operator_name,o2.name parent_name
        ,(select count(*) from {$s} s,{$so} so where s.id = so.student_id and so.status = {$studentOrgStatus} and 
        s.status = {$studentStatus} and o.id = so.org_id) student_amount
        ,(select count(*) from {$t} t,{$too} too where t.id = too.teacher_id 
        and too.status = {$teacherOrgStatus} and t.status = {$teacherStatus} and o.id = too.org_id) teacher_amount
        ,(select count(*) from {$e} e where e.org_id = o.id) employee_amount 
        from {$o} o
        left join {$e} e on o.operator_id = e.id
        left join {$o} o2 on o.parent_id = o2.id
        left join {$e} e2 on e2.org_id = o.id
        left join {$ro} ro on ro.id = e2.role_id and ro.id in (".self::PRINCIPAL_SPECIAL.",".self::PRINCIPAL_NORMAL.")
        {$where}
        order by o.create_time desc
        {$limit}", $map);

        $total = $db->queryAll("select count(*) count
        from {$o} o
        left join {$e} e2 on e2.org_id = o.id
        left join {$ro} ro on ro.id = e2.role_id and ro.id = :role_id
        {$where}
        {$limit}", $map);

        return [$records, $total[0]['count']];
    }

    /** 根据ID查询一条机构记录
     * @param $orgId
     * @return array|null
     */
    public static function getInfo($orgId)
    {
        $db = MysqlDB::getDB();
        $records = $db->queryAll("select o.*,o2.name parent_name from organization o left join 
        organization o2 on o.parent_id = o2.id where o.id = :org_id",[':org_id' => $orgId]);
        return empty($records) ? [] : $records[0];
    }
}
