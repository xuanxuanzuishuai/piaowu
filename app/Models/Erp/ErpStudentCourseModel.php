<?php

namespace App\Models\Erp;

use App\Libs\MysqlDB;

class ErpStudentCourseModel extends ErpModel
{
    public static $table = 'erp_student_course';
    /** @var int 课包状态 */
    const STATUS_ABOLISH = 0; // 废除
    const STATUS_NORMAL = 1;  // 正常
    const STATUS_EXPIRE = 2;  // 过期
    const STATUS_TRANSED = 3; // 已转移

    const BUSINESS_TYPE_NORMAL = 1; // 正式课

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

    /**
     * 获取用户清退后是否再购买正式课
     * @param $studentId
     * @param $firstCleanTime
     * @return array
     */
    public static function getStudentCleanAfterHasBuyCourse($studentId, $firstCleanTime = 0): array
    {
        if (empty($firstCleanTime)) {
            $firstCleanInfo = ErpStudentCourseTmpModel::getRecord(['student_id' => $studentId, 'ORDER' => ['id' => 'DESC']], ['create_time']);
            $firstCleanTime = $firstCleanInfo['create_time'] ?? 0;
            if (empty($firstCleanTime)) {
                return [];
            }
        }
        $sql = 'select id from ' . self::getTableNameWithDb() . ' WHERE student_id=' . $studentId .
            ' AND create_time >' . $firstCleanTime .
            ' AND business_type=' . self::BUSINESS_TYPE_NORMAL .
            " AND json_extract(business_tag,'$.price') >0" .
            " AND json_search(business_tag,'one','free_type') IS NULL" .
            ' LIMIT 1';
        $info = MysqlDB::getDB()->queryAll($sql);
        return is_array($info) ? $info : [];
    }
}