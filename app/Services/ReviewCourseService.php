<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/11/22
 * Time: 11:47 AM
 */

namespace App\Services;


use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\OpernCenter;
use App\Libs\Util;
use App\Models\PlayRecordModel;
use App\Models\ReviewCourseModel;
use App\Models\StudentModel;

class ReviewCourseService
{
    /**
     * 更新点评课标记
     * has_review_course = 0没有|1课包49|2课包1980
     * 只能从低级更新到高级
     *
     * @param $studentID
     * @param $reviewCourseType
     * @return null|string errorCode
     */
    public static function updateReviewCourseFlag($studentID, $reviewCourseType)
    {
        $student = StudentModel::getById($studentID);
        if ($student['has_review_course'] >= $reviewCourseType) {
            return null;
        }

        $affectRows = StudentModel::updateRecord($studentID, [
            'has_review_course' => $reviewCourseType,
        ], false);

        if($affectRows == 0) {
            return 'update_student_fail';
        }

        return null;
    }

    /**
     * 根据订单的 packageId 获取点评课标记
     * @param $packageId
     * @return int
     */
    public static function getBillReviewCourseType($packageId)
    {
        $package49 = DictConstants::get(DictConstants::WEB_STUDENT_CONFIG, 'package_id');
        if ($packageId == $package49) {
            return ReviewCourseModel::REVIEW_COURSE_49;
        }

        $package1980 = DictConstants::get(DictConstants::WEB_STUDENT_CONFIG, 'plus_package_id');
        if ($packageId == $package1980) {
            return ReviewCourseModel::REVIEW_COURSE_1980;
        }

        return ReviewCourseModel::REVIEW_COURSE_NO;
    }

    /**
     * 点评课学生列表过滤条件
     * @param $filterParams
     * @return array
     */
    public static function studentsFilter($filterParams)
    {
        $filter = [];

        if (isset($filterParams['course_status'])) {
            $op = $filterParams['course_status'] == 1 ? '[>=]' : '[<]';
            $filter['sub_end_date' . $op] = date('Ymd');
        }

        if (isset($filterParams['last_play_after'])) {
            $filter['last_play_time[>=]'] = $filterParams['last_play_after'];
        }

        if (isset($filterParams['last_play_before'])) {
            $filter['last_play_time[<]'] = $filterParams['last_play_before'];
        }

        if (isset($filterParams['sub_start_after'])) {
            $filter['sub_start_time[>=]'] = $filterParams['sub_start_after'];
        }

        if (isset($filterParams['sub_start_before'])) {
            $filter['sub_start_time[<]'] = $filterParams['sub_start_before'];
        }

        if (isset($filterParams['sub_end_after'])) {
            $filter['sub_end_time[>=]'] = $filterParams['sub_end_after'];
        }

        if (isset($filterParams['sub_end_before'])) {
            $filter['sub_end_time[<]'] = $filterParams['sub_end_before'];
        }

        list($page, $count) = Util::formatPageCount($filterParams);
        $filter['LIMIT'] = [($page - 1) * $count, $count];

        return $filter;
    }

    /**
     * 点评课学生列表
     * @param array $filter
     * @return array
     */
    public static function students($filter)
    {
        $students = ReviewCourseModel::students($filter);

        $students = array_map(function ($student) {

            switch ($student['has_review_course']) {
                case ReviewCourseModel::REVIEW_COURSE_1980:
                    $reviewCourseType = '1980';
                    break;
                case ReviewCourseModel::REVIEW_COURSE_49:
                    $reviewCourseType = '49';
                    break;
                default:
                    $reviewCourseType = '无';
            }

            return [
                'id' => (int)$student['id'],
                'name' => $student['name'],
                'mobile' => $student['mobile'],
                'course_status' => ($student['sub_end_date'] >= date('Ymd')) ? '已完课' : '未完课',
                'last_play_time' => $student['last_play_time'],
                'last_review_time' => $student['last_review_time'],
                'wx_bind' => empty($student['open_id']) ? '否' : '是',
                'review_course' => $reviewCourseType,
            ];
        }, $students);

        return $students;
    }

    /**
     * 点评课学生信息
     * @param int $studentId
     * @return array
     * @throws RunTimeException
     */
    public static function studentInfo($studentId)
    {
        $student = StudentModel::getById($studentId);
        if (empty($student)) {
            throw new RunTimeException(['unknown_user']);
        }

        return [
            'id' => $student['id'],
            'name' => $student['name'],
            'mobile' => $student['mobile'],
        ];
    }

    /**
     * 日报列表过滤条件
     * @param array $filterParams
     * @return array
     */
    public static function reportsFilter($filterParams)
    {
        $filter = [];

        if (isset($filterParams['play_after'])) {
            $filter['created_time[>=]'] = $filterParams['play_after'];
        }

        if (isset($filterParams['play_before'])) {
            $filter['created_time[<]'] = $filterParams['play_before'];
        }

        if (isset($filterParams['student_id'])) {
            $filter['student_id'] = $filterParams['student_id'];
        }

        list($page, $count) = Util::formatPageCount($filterParams);
        $filter['LIMIT'] = [($page - 1) * $count, $count];

        return $filter;
    }

    /**
     * 日报列表
     * @param array $filter
     * @return array
     */
    public static function reports($filter)
    {
        $reports = ReviewCourseModel::reports($filter);
        $reports = array_map(function ($report) {
            return [
                'date' => $report['date'],
                'review_time' => 0,
                'review_teacher' => '无',
            ];
        }, $reports);

        return $reports;
    }

    /**
     * 日报详情过滤条件
     * @param array $filterParams
     * @return array
     * @throws RunTimeException
     */
    public static function reportDetailFilter($filterParams)
    {
        $filter = [];

        $dayTime = strtotime($filterParams['play_date']);
        if (empty($dayTime)) {
            throw new RunTimeException(['invalid_date']);
        }
        $filter['created_time[<>]'] = [$dayTime, $dayTime + 86399];

        $filter['student_id'] = $filterParams['student_id'];

        if (isset($filterParams['lesson_id'])) {
            $filter['lesson_id'] = $filterParams['lesson_id'];
        }

        return $filter;
    }

    /**
     * 日报详情
     * 内容按曲目id汇总
     * @param $filter
     * @return array
     */
    public static function reportDetail($filter)
    {
        $lessons = ReviewCourseModel::reportDetail($filter);

        $lessonIds = array_column($lessons, 'lesson_id');
        if (!empty($lessonIds)) {
            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, 'test', 0, 1);
            $resp = $opn->lessonsByIds($lessonIds, 0, []);
            if ($resp['code'] == 0) {
                $lessonsInfo = $resp['data'];
                $lessonsInfo = array_combine(array_column($lessonsInfo, 'id'), $lessonsInfo);
            }
        }

        if (empty($lessonsInfo)) { $lessonsInfo = []; }
        $lessons = array_map(function ($lesson) use ($lessonsInfo) {
            return [
                'lesson_id' => (int)$lesson['lesson_id'],
                'lesson_name' => $lessonsInfo[$lesson['lesson_id']]['lesson_name'] ?? '-',
                'collection_id' => $lessonsInfo[$lesson['lesson_id']]['collection_id'] ?? 0,
                'collection_name' => $lessonsInfo[$lesson['lesson_id']]['collection_name'] ?? '-',
                'total_time' => (int)$lesson['total_time'],
                'total_count' => (int)$lesson['total_count'],
                'ai_count' => (int)$lesson['ai_count'],
                'ai_max_score' => $lesson['ai_max_score'],
                'dynamic_count' => $lesson['total_count'] - $lesson['ai_count']

            ];
        }, $lessons);

        return $lessons;
    }

    /**
     * 日报详情动态演奏
     * @param array $filter
     * @return array
     */
    public static function reportDetailDynamic($filter)
    {
        $items = ReviewCourseModel::reportDetailDynamic($filter);
        $items = array_map(function ($item) {

            switch ($item['cfg_hand']) {
                case PlayRecordModel::CFG_HAND_LEFT:
                    $cfgHand = '左手';
                    break;
                case PlayRecordModel::CFG_HAND_RIGHT:
                    $cfgHand = '右手';
                    break;
                default:
                    $cfgHand = '双手';
            }

            switch ($item['cfg_mode']) {
                case PlayRecordModel::CFG_MODE_STEP:
                    $cfgMode = '识谱';
                    break;
                case PlayRecordModel::CFG_MODE_SLOW:
                    $cfgMode = '慢练';
                    break;
                default:
                    $cfgMode = 'PK';
            }

            return [
                'frag_key' => $item['frag_key'],
                'cfg_hand_lang' => $cfgHand,
                'cfg_mode_lang' => $cfgMode,
                'count' => (int)$item['count'],
                'max_score' => $item['max_score'],
            ];
        }, $items);

        return $items;
    }

    /**
     * 日报详情AI测评
     * @param array $filter
     * @return array
     */
    public static function reportDetailAI($filter)
    {
        $items = ReviewCourseModel::reportDetailAI($filter);

        $maxScore = -1;
        $maxScoreItemIdx = -1;

        $records = [];
        foreach ($items as $i => $item) {
            if ($item['score'] > $maxScore) {
                $maxScore = $item['score'];
                $maxScoreItemIdx = $i;
            }

            $records[$i] = [
                'ai_record_id' => $item['ai_record_id'],
                'created_time' => $item['created_time'],
                'score' => $item['score'],
                'is_frag_lang' => $item['is_frag'] ? '是' : '-',
                'is_max_score_lang' => '-'
            ];
        }

        if ($maxScoreItemIdx >= 0) {
            $records[$maxScoreItemIdx]['is_max_score_lang'] = '是';
        }

        return $records;
    }
}