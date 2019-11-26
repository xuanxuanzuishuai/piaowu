<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/11/22
 * Time: 12:00 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;
use Medoo\Medoo;

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

        $students = $db->select(self::$table . '(s)',
            [
                's.id',
                's.name',
                's.mobile',
                's.sub_end_date',
                's.has_review_course',
                's.last_play_time',
                's.last_review_time',
            ],
            $where
        );

        return $students;
    }

    /**
     * 点评课日报列表
     * @param array $where
     * @return array
     */
    public static function reports($where)
    {
        $db = MysqlDB::getDB();

        $where['ORDER'] = ['pr.id' => 'DESC'];
        $where['GROUP'] = Medoo::raw('FROM_UNIXTIME(pr.created_time, :date_format)', [':date_format' => "%Y-%m-%d"]);

        $reports = $db->select(PlayRecordModel::$table . '(pr)',
            [
                'date' => Medoo::raw('FROM_UNIXTIME(pr.created_time, :date_format)', [':date_format' => "%Y-%m-%d"])
            ],
            $where
        );

        return $reports;
    }

    /**
     * 点评课日报，曲目汇总表
     * @param array $where
     * @return array
     */
    public static function reportDetail($where)
    {
        $db = MysqlDB::getDB();

        $where['GROUP'] = ['lesson_id'];

        $lessons = $db->select(PlayRecordModel::$table . '(pr)',
            [
                'lesson_id',
                'ai_count' => Medoo::raw('SUM(lesson_type)'),
                'total_count' => Medoo::raw('COUNT(lesson_type)'),
                'total_time' => Medoo::raw('SUM(duration)'),
                'ai_max_score' => Medoo::raw('MAX(IF(lesson_type=1, score, 0))')
            ],
            $where
        );

        return $lessons;
    }

    /**
     * 日报动态演奏数据
     * @param array $where
     * @return array
     */
    public static function reportDetailDynamic($where)
    {
        $db = MysqlDB::getDB();

        $where['lesson_type'] = PlayRecordModel::TYPE_DYNAMIC;
        $where['GROUP'] = ['frag_key', 'cfg_hand', 'cfg_mode'];

        $lessons = $db->select(PlayRecordModel::$table . '(pr)',
            [
                'frag_key',
                'cfg_hand',
                'cfg_mode',
                'count' => Medoo::raw('COUNT(id)'),
                'max_score' => Medoo::raw('MAX(score)'),
            ],
            $where
        );

        return $lessons;
    }

    /**
     * 日报AI测评数据
     * @param array $where
     * @return array
     */
    public static function reportDetailAI($where)
    {
        $db = MysqlDB::getDB();

        $where['lesson_type'] = PlayRecordModel::TYPE_AI;

        $lessons = $db->select(PlayRecordModel::$table . '(pr)',
            [
                'ai_record_id',
                'created_time',
                'score',
                'is_frag',
                'cfg_hand',
                'cfg_mode',
            ],
            $where
        );

        return $lessons;
    }
}