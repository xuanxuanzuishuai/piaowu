<?php


namespace App\Services;

use App\Libs\Constants;
use App\Models\StudentLearnRecordModel;
use App\Models\StudentModel;
use App\Models\StudentSignUpCourseModel;


class InteractiveClassroomService
{
    /**
     * @return array[]
     * 获取推荐课程或可预约课程
     */
    public static function recommendCourse()
    {
        return [
            [
                'collection_id'    => 1,
                'collection_name'  => "哈农NO.1 第一课",
                "collection_start_week" => "4",
                "collection_start_time" => "11:40",
                'collection_tags'  => ['基本功', '启蒙'],
                'collection_img'   => 'www.baidu.com',
                'is_new'           => true,
                'lesson_count' => 4
            ],
            [
                'collection_id'    => 2,
                'collection_name'  => "哈农NO.1 第二课",
                "collection_start_week" => "4",
                "collection_start_time" => "10:40",
                'collection_tags'  => ['基本功', '启蒙'],
                'collection_img'   => 'www.baidu.com',
                'is_new'           => true,
                'lesson_count' => 5
            ],
        ];
    }

    /**
     * @return array[]
     * 待上线课程
     */
    public static function preOnlineCourse()
    {
        return [
            [
                'collection_id'   => 1,
                'collection_name' => "哈农NO.1 第一课",
                'collection_img'  => 'www.baidu.com',
                'expect_num'      => '6000',
                'is_expect'       => true
            ],
            [
                'collection_id'   => 2,
                'collection_name' => "哈农NO.1 第二课",
                'collection_img'  => 'www.baidu.com',
                'expect_num'      => '6000',
                'is_expect'       => true
            ],
        ];
    }

    /**
     * @return array[]
     * 制作中课程
     */
    public static function makingCourse()
    {
        return [
            [
                'collection_id'   => 1,
                'collection_name' => "哈农NO.1 第一课",
                'collection_img'  => 'www.baidu.com',
            ],
            [
                'collection_id'   => 2,
                'collection_name' => "哈农NO.1 第二课",
                'collection_img'  => 'www.baidu.com',
            ],
        ];
    }

    /**
     * 可报名课程
     * @param $studentId
     * @return array
     */
    public static function getSignUpCourse($studentId)
    {
        $student = StudentModel::getById($studentId);
        if (empty($student)) {
            return [];
        }
        //获取所有可报名课包
        $singUpCourse = self::recommendCourse();
        $singUpCourseData = array_combine(array_column($singUpCourse, 'collection_id'), $singUpCourse);
        //获取用户购买的课包
        $studentBingCourse = StudentSignUpCourseModel::getRecords(['student_id'=> $studentId, 'bind_status' => StudentSignUpCourseModel::COURSE_BING_STATUS_SUCCESS]);
        $studentBingCourseData = array_combine(array_column($studentBingCourse, 'collection_id'), $studentBingCourse);
        //统计用户已上课次数
        $StudentLearnCount = StudentLearnRecordModel::getStudentLearnCount($studentId);

        foreach ($singUpCourseData as $key => $value){
            $value['course_bind_status'] = $studentBingCourseData[$value['collection_id']] ? (INT)$studentBingCourseData[$value['collection_id']]['bind_status'] : StudentSignUpCourseModel::COURSE_NOT_BING_STATUS;
            $value['attend_class_count'] = $studentBingCourseData[$value['collection_id']] ? (INT)$StudentLearnCount[$value['collection_id']]['attend_class_count'] : Constants::STATUS_FALSE;
            $result[] = $value;
        }
        return $result ?? [];
    }


    /**
     * 待发布课程
     * @return array|array[]
     */
    public static function getToBeLaunchedCourse()
    {
        $preOnLineCourse = self::preOnlineCourse();
        return $preOnLineCourse ?? [];
    }

    /**
     * 获取制作中课程
     * @return array|array[]
     */
    public static function getInProductionCourse()
    {
        $preOnLineCourse = self::makingCourse();
        return $preOnLineCourse ?? [];
    }
}