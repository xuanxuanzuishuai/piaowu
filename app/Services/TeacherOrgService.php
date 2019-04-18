<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/17
 * Time: 下午9:06
 */

namespace App\Services;


use App\Models\TeacherOrg;

class TeacherOrgService
{
    /**
     * 根据机构和老师ID查询一条记录
     * @param $orgId
     * @param $teacherId
     * @return mixed
     */
    public static function getTeacherByOrgAndId($orgId, $teacherId)
    {
        return TeacherOrg::getRecord([
            'org_id'     => $orgId,
            'teacher_id' => $teacherId,
            'status'     => TeacherOrg::STATUS_NORMAL,
        ]);
    }
}