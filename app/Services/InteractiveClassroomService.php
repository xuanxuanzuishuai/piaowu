<?php


namespace App\Services;

use App\Libs\Constants;
use App\Libs\HttpHelper;
use App\Models\StudentLearnRecordModel;
use App\Models\StudentModel;
use App\Models\StudentSignUpCourseModel;
use App\Libs\Exceptions\RunTimeException;
use App\Models\ReviewCourseModel;
use App\Libs\Util;

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
        $studentBingCourse = StudentSignUpCourseModel::getRecords(['student_id'=> $studentId, 'bind_status' => StudentSignUpCourseModel::COURSE_BING_SUCCESS]);
        $studentBingCourseData = array_combine(array_column($studentBingCourse, 'collection_id'), $studentBingCourse);
        //统计用户已上课次数
        $StudentLearnCount = StudentLearnRecordModel::getStudentLearnCount($studentId);

        foreach ($singUpCourseData as $key => $value){
            $value['course_bind_status'] = $studentBingCourseData[$value['collection_id']] ? (INT)$studentBingCourseData[$value['collection_id']]['bind_status'] : StudentSignUpCourseModel::COURSE_BING_ERROR;
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
                $item['course_bind_status'] = StudentSignUpCourseModel::COURSE_BING_ERROR;
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
                $item['course_bind_status'] = StudentSignUpCourseModel::COURSE_CLASS_IN;
            } else {
                $item['course_bind_status'] = StudentSignUpCourseModel::COURSE_CLASS_TO_BE_STARTED;;
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
            $value['course_bind_status'] = StudentSignUpCourseModel::COURSE_CLASS_TO_BE_STARTED;
        });

        return $platformCoursePlan ?? [];
    }

    /**
     * 用户报名课程
     * @param $studentId
     * @param $collectionId
     * @param $lessonCount
     * @param $startWeek
     * @param $startTime
     * @return bool|int|mixed|string
     * @throws RunTimeException
     */
    public static function collectionSignUp($studentId, $collectionId, $lessonCount, $startWeek, $startTime)
    {

        $student = StudentModel::getById($studentId);
        if (empty($student)) {
            throw new RunTimeException(['unknown_user']);
        }

        //只有年卡用户并且在使用时间范围内，才可报名
        if ($student['has_review_course'] != ReviewCourseModel::REVIEW_COURSE_1980 || $student['sub_end_date'] < date('Ymd', time())) {
            throw new RunTimeException(['please_buy_the_annual_card']);
        }

        $signUpData = StudentSignUpCourseModel::getRecord(['student_id' => $studentId, 'collection_id' => $collectionId]);
        $time = time();
        if ($signUpData) {
            StudentSignUpCourseModel::updateRecord($signUpData['id'], ['bind_status' => StudentSignUpCourseModel::COURSE_BING_SUCCESS, 'update_time' => $time]);
        } else {
            //获取用户开始上课&结束上课的时间戳
            list($firstCourseTime, $lastTime) = self::calculationCourseTime($time, $startWeek, $startTime, $lessonCount);

            StudentSignUpCourseModel::insertRecord(['student_id' => $studentId, 'collection_id' => $collectionId, 'bind_status' => StudentSignUpCourseModel::COURSE_BING_SUCCESS, 'lesson_count' => $lessonCount, 'start_week' => $startWeek,  'start_time' => strtotime(date('Y-m-d').$startTime), 'create_time' => $time, 'update_time' => $time, 'last_course_time' => $lastTime, 'first_course_time' => $firstCourseTime]);
        }
        StudentSignUpCourseModel::delStudentMonthRedis($studentId, $collectionId);
    }

    /**
     * 根据用户当前报名的周，和开课的周几做比对，计算出用户上课的开始&结束时间
     * @param $time
     * @param $startWeek
     * @param $startTime
     * @param $lessonCount
     * @return array
     */
    public static function calculationCourseTime($time, $startWeek, $startTime, $lessonCount)
    {
        $nowWeek = date("N", $time);
        if ($nowWeek == $startWeek) {
            $firstCourseTime = strtotime(date('Y-m-d').$startTime);
            $lastTime = $firstCourseTime + Util::TIMESTAMP_ONEDAY * StudentSignUpCourseModel::A_WEEK * ($lessonCount - 1);
        } elseif ($nowWeek > $startWeek) {
            $firstCourseTime = strtotime(date("Y-m-d ", strtotime("+" . $nowWeek - $startWeek ."day")) . $startTime);
            $lastTime = $firstCourseTime + Util::TIMESTAMP_ONEDAY * StudentSignUpCourseModel::A_WEEK * ($lessonCount - 1);
        } elseif ($nowWeek < $startWeek) {
            $firstCourseTime = strtotime(date("Y-m-d ", strtotime("+" . StudentSignUpCourseModel::A_WEEK - $nowWeek + $startWeek ."day")) . $startTime);
            $lastTime = $firstCourseTime + Util::TIMESTAMP_ONEDAY * StudentSignUpCourseModel::A_WEEK * ($lessonCount - 1);
        } else {
            $firstCourseTime = Constants::STATUS_FALSE;
            $lastTime = Constants::STATUS_FALSE;
        }
        return [$firstCourseTime, $lastTime];
    }


    /**
     * 用户取消报名
     * @param $studentId
     * @param $collectionId
     * @return bool|int|mixed|string
     * @throws RunTimeException
     */
    public static function cancelSignUp($studentId, $collectionId)
    {
        $student = StudentModel::getById($studentId);
        if (empty($student)) {
            throw new RunTimeException(['unknown_user']);
        }

        $signUpData = StudentSignUpCourseModel::getRecord(['student_id' => $studentId, 'collection_id' => $collectionId]);
        if (empty($signUpData)) {
            throw new RunTimeException(['record_not_found']);
        }
        StudentSignUpCourseModel::updateRecord($signUpData['id'], ['bind_status' => StudentSignUpCourseModel::COURSE_BING_CANCEL, 'update_time' => time()]);
        StudentSignUpCourseModel::delStudentMonthRedis($studentId, $collectionId);
    }

    /**
     * 用户上课记录
     * @param $studentId
     * @param $collectionId
     * @param $lessonId
     * @param $learnStatus 1完成上课 2完成补课
     * @param $createTime //这个时间是，用户上的/补的哪天的课
     * @throws RunTimeException
     */
    public static function studentLearnRecode($studentId, $collectionId, $lessonId, $learnStatus, $createTime)
    {
        $student = StudentModel::getById($studentId);
        if (empty($student)) {
            throw new RunTimeException(['record_not_found']);
        }
        //不是年卡用户不可以上课
        if ($student['has_review_course'] != ReviewCourseModel::REVIEW_COURSE_1980 || $student['sub_end_date'] < date('Ymd')) {
            throw new RunTimeException(['please_buy_the_annual_card']);
        }

        $time = time();
        $insertData = [
            'student_id' => $studentId,
            'collection_id' => $collectionId,
            'lesson_id' => $lessonId,
            'learn_status' => $learnStatus,
            'create_time' => strtotime($createTime),
            'update_time' => $time
        ];
        $insertRes = StudentLearnRecordModel::insertRecord($insertData, false);
        if (empty($insertRes)) {
            throw new RunTimeException(['insert_failure']);
        }
        StudentSignUpCourseModel::delStudentMonthRedis($studentId, $collectionId);
    }

    /**
     * 获取学生上课日历
     * @param $studentId
     * @param $year
     * @param $month
     * @return array
     */

    public static function getLearnCalendar($studentId, $year, $month)
    {

        $startTime = strtotime($year . "-" . $month);
        $endTime = strtotime('+1 month', $startTime) - 1;

        //获取学生报名的所有课包
        $studentAllBindCourse = StudentSignUpCourseModel::getStudentBindCourse($studentId, $startTime, $endTime);
        if (empty($studentAllBindCourse)) return [];
        $formatStudentAllBindCourse = self::formatClassDate($studentAllBindCourse);
        $studentAllBindCourseRes = array_combine(array_column($formatStudentAllBindCourse, 'class_time'), $formatStudentAllBindCourse);

        //获取学生的上课记录
        $studentLearnRecord = StudentLearnRecordModel::getStudentLearnCalendar($studentId, $startTime);
        if (!empty($studentLearnRecord)) {
            $studentLearnRecordData = array_combine(array_column($studentLearnRecord, 'class_record_time'), $studentLearnRecord);
        } else {
            $studentLearnRecordData = [];
        }

        //计算本月的所有上课状态，是否缺课
        foreach ($studentAllBindCourseRes as $item) {
            if (substr($item['class_time'], 4, -2) != $month) {
                continue;
            }
            $classStatus = self::calculationClassStatus($item, $studentLearnRecordData);
            $item['class_status'] = $classStatus;
            $resultRes[] = $item;
        }
        return $resultRes;
    }

    /**
     * 处理学生上课状态，是否有缺课
     * @param $item
     * @param $studentLearnRecordData
     * @param $studentTodayBindCourseRes
     * @return int
     */
    public static function calculationClassStatus($item, $studentLearnRecordData)
    {
        $time = time();
        //处理小于当前时间的上课状态,并且有上课记录，并且上课记录等于当天用户购买的课节数
        if ($item['class_time'] < date('Ymd', $time) && $item['class_time'] == $studentLearnRecordData[$item['class_time']]['class_record_time'] && $item['class_count'] == $studentLearnRecordData[$item['class_time']]['class_record_count']) {
            $classStatus = StudentSignUpCourseModel::COURSE_CLASS_NOT_ABSENTEEISM_STATUS;
        } elseif ($item['class_time'] > date('Ymd', $time)) { //大于当前时间的课包，处于待开课状态
            $classStatus = StudentSignUpCourseModel::COURSE_CLASS_TO_BE_STARTED;
        } elseif ($item['class_time'] == date('Ymd', $time)) { //当天的状态，后端不做处理,设计实时问题，会在日历详情把当天的课包&上课记录返回给前端
            $classStatus = Constants::STATUS_FALSE;
        } else {
            $classStatus = StudentSignUpCourseModel::COURSE_CLASS_ABSENTEEISM_STATUS;
        }

        return $classStatus;
    }

    /**
     * 根据用户第一节课开课时间，以及课包总共的节数，推断出用户哪一天有课
     * @param $studentBindCourse
     * @return array
     */
    public static function formatClassDate($studentBindCourse)
    {
        $formatDate = [];
        foreach ($studentBindCourse as $item) for ($i = 0; $i < $item['lesson_count']; $i++) if ($i == 0) {
            $formatDate[] = date('Ymd', $item['first_course_time']);
        } else {
            $last_time = array_pop($formatDate);
            array_push($formatDate,$last_time);
            $formatDate[] = date('Ymd',strtotime($last_time.'+7day'));
        }
        $formatDateRes = array_count_values($formatDate);
        $formatDateResult = [];
        reset($formatDateRes);
        while (list($key, $val) = each($formatDateRes)) {
            $formatDateResult[] = array('class_time'=>$key,'class_count'=>$val);
        }
        return $formatDateResult;
    }

    /**
     * 获取上课日历详情
     * @param $studentId
     * @param $year
     * @param $month
     * @param $day
     * @return mixed
     */
    public static function getCalendarDetails($studentId, $year, $month, $day)
    {
        $student = StudentModel::getById($studentId);
        if (empty($student)) {
            return [];
        }
        //判断用户是否年卡用户，年卡是否过期
        if ($student['has_review_course'] == ReviewCourseModel::REVIEW_COURSE_1980 && $student['sub_end_date'] < date('Ymd', time())) {
            $result['student_status'] = ReviewCourseModel::REVIEW_COURSE_1980;
        } elseif ($student['has_review_course'] == ReviewCourseModel::REVIEW_COURSE_1980 && $student['sub_end_date'] > date('Ymd', time())) {
            $result['student_status'] = ReviewCourseModel::REVIEW_COURSE_BE_OVERDUE;
        } else {
            $result['student_status'] = $student['has_review_course'];
        }

        //没有传时间，默认时间为今天
        if (empty($year) && empty($month) && empty($day)) {
            $date = date('Y-m-d', time());
        } else {
            $date = $year.'-'.$month.'-'.$day;
        }
        //练琴时长
        $startTime = strtotime($date);
        $endTime = $startTime + 86400 -1;
        //今日课程
        $todayClass = self::studentCoursePlan($studentId, $startTime);
        //如果获取的是今日数据，需要把用户今天的练琴记录返回客户端，客户端实时更新课程状态
        if ($date == date('Y-m-d')) {
            $todayLearn = StudentLearnRecordModel::getRecords(['student_id' => $studentId, 'create_time[>=]' => $startTime]);
        } else{
            $todayLearn = [];
        }

        $result['sum_duration'] = AIPlayRecordService::getStudentDaySumDuration($studentId, $startTime, $endTime);
        //练琴任务
        list($generalTask,$finishTheTask) = CreditService::getActivityList($studentId);
        $result['finish_the_task'] = (INT)$finishTheTask;   //完成练琴任务次数
        $result['general_task'] = $generalTask;//总任务
        $result['today_class'] = $todayClass;
        $result['today_learn'] = $todayLearn;
        $result['date'] = date('Y-m-d', time());
        return $result;
    }

}