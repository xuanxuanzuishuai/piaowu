<?php

namespace App\Services;

use App\Models\Erp\ErpStudentCourseModel;

class ErpCourseService
{
    /**
     * 获取账户可用课程：不同类型的课程
     * @param $studentId
     * @param $courseType
     * @return int|mixed
     */
    public static function getUserRemainCourseNum($studentId, $courseType)
    {

        $courseInfo = ErpStudentCourseModel::getUserRemainCourseNum($studentId, $courseType);
        $allNum = 0;
        if (!empty($courseInfo)) {
            foreach ($courseInfo as $value) {
                $allNum += ($value['lesson_count'] + $value['free_count']);
            }
        }
        return $allNum;
    }
}
