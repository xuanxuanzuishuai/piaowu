<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/11/22
 * Time: 11:47 AM
 */

namespace App\Services;


use App\Libs\DictConstants;
use App\Libs\Util;
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
            $filter['sub_end_date'] = $op . date('Ymd');
        }

        if (isset($filterParams['last_play_after'])) {
            $filter['last_play_time'] = '[>=]' . $filterParams['last_play_after'];
        }

        if (isset($filterParams['last_play_before'])) {
            $filter['last_play_time'] = '[<]' . $filterParams['last_play_before'];
        }

        if (isset($filterParams['sub_start_after'])) {
            $filter['sub_start_time'] = '[>=]' . $filterParams['sub_start_after'];
        }

        if (isset($filterParams['sub_start_before'])) {
            $filter['sub_start_time'] = '[<]' . $filterParams['sub_start_before'];
        }

        if (isset($filterParams['sub_end_after'])) {
            $filter['sub_end_time'] = '[>=]' . $filterParams['sub_end_after'];
        }

        if (isset($filterParams['sub_end_before'])) {
            $filter['sub_end_time'] = '[<]' . $filterParams['sub_end_before'];
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
            return [
                'id' => (int)$student['id'],
                'name' => $student['name'],
                'mobile' => $student['mobile'],
                'course_status' => ($student['sub_end_date'] >= date('Ymd')) ? '已完课' : '未完课',
                'last_play_time' => $student['last_play_time'],
                'last_review_time' => $student['last_review_time'],
                'wx_bind' => empty($student['open_id']) ? '否' : '是'
            ];
        }, $students);

        return $students;
    }
}