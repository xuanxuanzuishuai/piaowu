<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/11/22
 * Time: 12:00 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;

class ReviewCourseModel extends Model
{
    protected static $table = 'student';

    // 点评课学生标记
    const REVIEW_COURSE_NO = 0; // 非点评课学生
    const REVIEW_COURSE_49 = 1; // 21天49元小课包
    const REVIEW_COURSE_1980 = 2; // 1年1980元大课包

    /**
     * 点评课学生列表
     * @param array $where
     * @return array
     */
    public static function students($where)
    {
        $db = MysqlDB::getDB();

        if (empty($where['has_review_course'])) {
            $where['has_review_course'] = [self::REVIEW_COURSE_49, self::REVIEW_COURSE_1980];
        }

        $where['uwx.user_type'] = UserWeixinModel::USER_TYPE_STUDENT;
        $where['uwx.busi_type'] = UserWeixinModel::BUSI_TYPE_STUDENT_SERVER;

        $students = $db->select(self::$table . '(s)',
            [
                '[>]' . UserWeixinModel::$table . '(uwx)' => ['s.id' => 'user_id']
            ],
            [
                's.id',
                's.name',
                's.mobile',
                's.sub_end_date',
                's.has_review_course',
                's.last_play_time',
                's.last_review_time',
                'uwx.open_id'
            ],
            $where
        );

        return $students;
    }
}