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

    const PRINCIPAL_NORMAL = 21; // role_id机构校长
    const PRINCIPAL_SPECIAL = 17; // role_id升级版机构校长

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
        $oa  = OrgAccountModel::$table;
        $a   = AreaModel::$table;

        $studentStatus    = StudentModel::STATUS_NORMAL;
        $teacherStatus    = TeacherModel::STATUS_NORMAL;
        $studentOrgStatus = StudentOrgModel::STATUS_NORMAL;
        $teacherOrgStatus = TeacherOrgModel::STATUS_NORMAL;

        $where = ' where 1=1 ';
        $map   = [];

        $db = MysqlDB::getDB();

        $records = $db->queryAll("select 
        a.name province_name, a2.name city_name, a3.name district_name, 
        o.*,e.name operator_name,o2.name parent_name
        ,(select oa.account from {$oa} oa where oa.org_id = o.id limit 1) account
        ,(select count(*) from {$s} s,{$so} so where s.id = so.student_id and so.status = {$studentOrgStatus} and 
        s.status = {$studentStatus} and o.id = so.org_id) student_amount
        ,(select count(*) from {$t} t,{$too} too where t.id = too.teacher_id 
        and too.status = {$teacherOrgStatus} and t.status = {$teacherStatus} and o.id = too.org_id) teacher_amount
        ,(select count(*) from {$e} e where e.org_id = o.id) employee_amount 
        ,(select concat_ws(',',name,mobile,login_name) from {$e} where role_id
        in(".self::PRINCIPAL_SPECIAL.",".self::PRINCIPAL_NORMAL.") and org_id = o.id order by e.id desc limit 1) principal
        from {$o} o
        left join {$e} e on o.operator_id = e.id
        left join {$o} o2 on o.parent_id = o2.id
        left join {$a} a on a.code = o.province_code
        left join {$a} a2 on a2.code = o.city_code
        left join {$a} a3 on a3.code = o.district_code
        {$where}
        order by o.create_time desc
        {$limit}", $map);

        $total = $db->queryAll("select count(*) count from {$o} o", $map);

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
