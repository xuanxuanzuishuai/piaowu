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

        $studentStatus    = StudentModel::STATUS_NORMAL;
        $teacherStatus    = TeacherModel::STATUS_NORMAL;
        $studentOrgStatus = StudentOrgModel::STATUS_NORMAL;
        $teacherOrgStatus = TeacherOrgModel::STATUS_NORMAL;

        $db = MysqlDB::getDB();

        $records = $db->queryAll("select o.*,e.name operator_name,o2.name parent_name
        ,(select count(*) from {$s} s,{$so} so where s.id = so.org_id and so.status = {$studentOrgStatus} and 
        s.status = {$studentStatus} and o.id = so.org_id) student_amount
        ,(select count(*) from {$t} t,{$too} too where t.id = too.teacher_id 
        and too.status = {$teacherOrgStatus} and t.status = {$teacherStatus} and o.id = too.org_id) teacher_amount
        ,(select count(*) from {$e} e where e.org_id = o.id) employee_amount 
        from {$o} o
        left join {$e} e on o.operator_id = e.id
        left join {$o} o2 on o.parent_id = o2.id {$limit}");

        $total = $db->count(self::$table);

        return [$records, $total];
    }

    /**
     *
     */
    public static function generateQrCode(){

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
