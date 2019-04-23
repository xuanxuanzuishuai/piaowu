<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/17
 * Time: 下午9:06
 */

namespace App\Services;


use App\Models\TeacherOrgModel;
use App\Models\OrganizationModel;

class TeacherOrgService
{
    /**
     * 根据机构和老师ID查询一条记录
     * @param $orgId
     * @param $teacherId
     * @param $status null表示不限制状态
     * @return mixed
     */
    public static function getTeacherByOrgAndId($orgId, $teacherId, $status = null)
    {
        $where = [
            'org_id'     => $orgId,
            'teacher_id' => $teacherId,
        ];
        if(!is_null($status)) {
            $where['status'] = $status;
        }
        return TeacherOrgModel::getRecord($where);
    }

    /**
     * @param $org_id
     * @param $teacher_id
     * @return int|null|void
     */
    public static function boundTeacher($org_id, $teacher_id){
        $orgObj = OrganizationModel::getById($org_id);
        if (empty($orgObj)) {
            return;
        }
        $boundInfo = TeacherOrgModel::getBoundInfo($org_id, $teacher_id);
        if (empty($boundInfo)){
            TeacherOrgModel::createBoundInfo($org_id, $teacher_id);
        } else {
            TeacherOrgModel::updateStatus($org_id, $teacher_id, TeacherOrgModel::STATUS_NORMAL);
        }
    }
}