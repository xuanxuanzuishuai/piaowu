<?php

namespace App\Services;


use App\Libs\Valid;
use App\Models\AIReferralToPandaUserModel;


class AIReferralToPandaUserService
{
    # 用户类型
    const USER_TYPE_LT4D = 1;
    const USER_TYPE_LT8D = 2;

    /**
     * 批量添加用户
     * @param $students
     * @param $userType
     * @return array | int
     */
    public static function addRecords($students, $userType)
    {
        $studentIds = array_column($students, 'id');
        $existStudents = self::getByStudentIds($studentIds);
        $existStudentIds = array_column($existStudents, 'student_id');
        $now = time();

        $data = [];
        foreach ($studentIds as $studentId) {
            if (!in_array($studentId, $existStudentIds)) {
                $dataItem = [
                    'student_id' => $studentId,
                    'create_time' => $now,
                    'update_time' => $now,
                    'user_type' => $userType
                ];
                $data[] = $dataItem;
            }
        }

        if (empty($data)) {
            return 0;
        }
        $result = AIReferralToPandaUserModel::batchInsert($data, false);
        if (!$result) {
            return Valid::addErrors([], 'insert_error', 'batch_insert_err');
        }
        return count($data);
    }

    /**
     * 根据学生id查询
     * @param $studentIds
     * @return array
     */
    public static function getByStudentIds($studentIds)
    {
        if (empty($studentIds)) {
            return [];
        }
        return AIReferralToPandaUserModel::getRecords(['student_id' => $studentIds], false);
    }
}
