<?php

namespace App\Models\Erp;

class ErpStudentCourseModel extends ErpModel
{
    public static $table = 'erp_student_course';
    /** @var int 课包状态 */
    const STATUS_ABOLISH = 0; // 废除
    const STATUS_NORMAL = 1;  // 正常
    const STATUS_EXPIRE = 2;  // 过期
    const STATUS_TRANSED = 3; // 已转移


    /**
     * 获取账户可用课程：不同类型的课程
     * @param $studentId
     * @param $courseType
     * @return array
     */
    public static function getUserRemainCourseNum($studentId, $courseType)
    {
        $db = self::dbRO();
        $courseData = $db->select(self::$table,
            [
                '[>]' . ErpCourseModel::$table => ['course_id' => 'id'],
                '[>]' . ErpStudentModel::$table => ['student_id' => 'id']
            ],
            [
                self::$table . '.lesson_count',
                self::$table . '.free_count',
                self::$table . '.freeze_count',
                self::$table . '.used_free_count',
                self::$table . '.used_lesson_count',
            ],
            [
                'AND' => [
                    self::$table . '.student_id' => $studentId,
                    ErpCourseModel::$table . '.type' => $courseType,
                    self::$table . '.status' => self::STATUS_NORMAL,
                    'OR' => [
                        'lesson_count[>]' => 0,
                        'free_count[>]' => 0
                    ]
                ]
            ]
        );
        return empty($courseData) ? [] : $courseData;
    }
}