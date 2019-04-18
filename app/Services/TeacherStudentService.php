<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/17
 * Time: 下午8:52
 */

namespace App\Services;

use App\Models\TeacherStudentModel;
use App\Libs\ResponseError;

class TeacherStudentService
{
    /**
     * 绑定学生
     * @param $orgId
     * @param $teacherId
     * @param $studentId
     * @return ResponseError|int|mixed|null|string
     */
    public static function bindStudent($orgId, $teacherId, $studentId)
    {
        $record = TeacherStudentModel::getRecord([
            'org_id'     => $orgId,
            'teacher_id' => $teacherId,
            'student_id' => $studentId,
        ]);

        if(empty($record)) {
            $now = time();
            $lastId = TeacherStudentModel::insertRecord([
                'teacher_id'  => $teacherId,
                'student_id'  => $studentId,
                'org_id'      => $orgId,
                'create_time' => $now,
                'update_time' => $now,
                'status'      => TeacherStudentModel::STATUS_NORMAL,
            ]);
            if(empty($lastId)) {
                return new ResponseError('save_fail');
            }
            return $lastId;
        } else {
            if($record['status'] == TeacherStudentModel::STATUS_NORMAL) {
                return new ResponseError('have_bind');
            } else {
                $affectRows = TeacherStudentModel::updateStatus($orgId, $teacherId, $studentId, TeacherStudentModel::STATUS_NORMAL);
                if($affectRows == 0) {
                    return new ResponseError('update_status_fail');
                }
                return $record['id'];
            }
        }
    }

    /**
     * 解绑学生
     * @param $orgId
     * @param $teacherId
     * @param $studentId
     * @return int|null
     */
    public static function unbindStudent($orgId, $teacherId, $studentId)
    {
        return TeacherStudentModel::updateStatus($orgId, $teacherId, $studentId, TeacherStudentModel::STATUS_STOP);
    }
}