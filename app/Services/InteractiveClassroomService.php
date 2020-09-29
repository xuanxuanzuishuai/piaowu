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
     * @param null $student_id
     * @param $date
     * @return \array[][]
     * 获取用户上课计划
     */
    public static function studentCoursePlan($student_id, $date)
    {
        return [
            [
                'lesson_id'           => 1,
                'lesson_name'         => "哈农NO.1 第一课",
                "lesson_start_time"   => "18:30",
                "lesson_learn_status" => 1,
                "collection_id" => 1,
                "lesson_cover" => 'www.baidu.com',
                "lesson_start_timestamp" => "1601100000"
            ],
            [
                'lesson_id'           => 2,
                'lesson_name'         => "哈农NO.1 第二课",
                "lesson_start_time"   => "17:30",
                "lesson_learn_status" => 1,
                "collection_id" => 1,
                "lesson_cover" => 'www.baidu.com',
                "lesson_start_timestamp" => "1601100000"
            ],
        ];
    }


    /**
     * @return \array[][]
     * 获取平台今明两天的开课计划
     */
    public static function platformCoursePlan()
    {
        return [
            'today'    => [
                [
                    'collection_id'         => 1,
                    'collection_name'       => "哈农NO.1 第一课",
                    "collection_start_time" => "20:00",
                    'collection_tags'       => ['基本功', '启蒙']
                ],
                [
                    'collection_id'         => 2,
                    'collection_name'       => "哈农NO.1 第二课",
                    "collection_start_time" => "19:35",
                    'collection_tags'       => ['基本功', '启蒙']
                ],
            ],
            'tomorrow' => [
                [
                    'collection_id'         => 1,
                    'collection_name'       => "哈农NO.1 第一课",
                    "collection_start_time" => "16:30",
                    'collection_tags'       => ['基本功', '启蒙']
                ],
                [
                    'collection_id'         => 2,
                    'collection_name'       => "哈农NO.1 第二课",
                    "collection_start_time" => "17:30",
                    'collection_tags'       => ['基本功', '启蒙']
                ],
            ]
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

    /**
     * 今日练琴计划，显示离当前时间最近的一节课，如果没计划返回今日推荐课程
     * @param $studentId
     * @return array|array[]
     */
    public static function getTodayCourse($studentId)
    {
        $date = date('Y-m-d');
        $time = time();
        //获取用户今日练琴计划->只要待上课的状态
        $studentTodayCoursePlan = self::studentCoursePlan($studentId, strtotime($date));
        foreach ($studentTodayCoursePlan as $item) {
            if ($item['lesson_learn_status'] == StudentLearnRecordModel::GO_TO_THE_CLASS) {
                $studentTodayCoursePlanData[] = $item;
            }
        }
        //如果不为空直接返回待上课的一节课程
        if (!empty($studentTodayCoursePlanData)) {
            usort($studentTodayCoursePlanData, function ($a, $b) {
                return $a['lesson_start_time'] > $b['lesson_start_time'];
            });
            return [$studentTodayCoursePlanData[0], []];
        }
        //没有今日上课计划，获取今日推荐课程
        $recommendCourse = self::recommendCourse();
        if (empty($recommendCourse)) {
            return [[],[]];
        }

        //判断今天是周几，并且当前课包是否有今天的课
        foreach ($recommendCourse as $item) {
            if ($item['collection_start_week'] == date("N", $time)) {
                //上课状态->去报名
                $item['course_bind_status'] = StudentSignUpCourseModel::COURSE_NOT_BING_STATUS;
                $todayRecommendCourse[] = $item;
            }
        }
        if (empty($todayRecommendCourse)) {
            return [[],[]];
        }

        //当天推荐最优先是开课中的课程
        foreach ($todayRecommendCourse as $item) {
            //计算课包结束时间，以半小时为边界
            $collection_start_time_stamp = strtotime(date("Y-m-d"). $item['collection_start_time']);
            $collection_end_time_stamp = $collection_start_time_stamp + StudentSignUpCourseModel::DURATION_30MINUTES;
            if ($time > $collection_start_time_stamp && $time < $collection_end_time_stamp) {
                return [[], $item];
            }
        }

        //没有开课中的课包，推荐最早开课的课包
        usort($todayRecommendCourse, function ($a, $b) {
            return $a['collection_start_time'] > $b['collection_start_time'];
        });
        return [[], $todayRecommendCourse[0]];
    }

    /**
     * 获取小喇叭轮播 小喇叭轮播当前时间往后的课程，包含上课中的课程，上课没有统一结束时间，都以开课之后的半小时为边界
     * @return array|\array[][]
     */
    public static function getSmallHornInfo()
    {
        //获取今明两天的上课计划,如果为空则不做任何处理
        $platformCoursePlan = self::platformCoursePlan();
        if (empty($platformCoursePlan['today']) && empty($platformCoursePlan['tomorrow'])) {
            return $platformCoursePlan;
        }
        //处理今天的数据，今天只显示当前时间往后的课程，并且包含正在开课的课程
        foreach ($platformCoursePlan['today'] as $item) {
            //计算课包结束时间，以半小时为边界
            $item['collection_end_time'] = strtotime(date("Y-m-d"). $item['collection_start_time']) + StudentSignUpCourseModel::DURATION_30MINUTES;
            $time = time();
            //当天轮播数据只显示当前时间戳往后的课包（除正在开课中的课包）
            if ($item['collection_end_time'] < $time) {
                continue;
            }
            if(strtotime(date("Y-m-d"). $item['collection_start_time']) < $time) {
                $item['course_bind_status'] = StudentSignUpCourseModel::IN_CLASS_STATUS;
            } else {
                $item['course_bind_status'] = StudentSignUpCourseModel::COURSE_TO_BE_STARTED;;
            }
            $item['collection_end_time'] = date("H:i", $item['collection_end_time']);
            $smallHornInfo[] = $item;
        }

        //今天的推荐按照开课时间排序，上课中的排在第一
        if (!empty($smallHornInfo)) {
            usort($smallHornInfo, function ($a, $b) {
                return $a['collection_start_time'] > $b['collection_start_time'];
            });
            $platformCoursePlan['today'] = $smallHornInfo;
        } else {
            $platformCoursePlan['today'] = [];
        }
        //明天的推荐新增课包开课的状态
        array_walk($platformCoursePlan['tomorrow'], function (&$value) {
            $value['course_bind_status'] = StudentSignUpCourseModel::COURSE_TO_BE_STARTED;
        });

        return $platformCoursePlan ?? [];
    }

}