<?php


namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\RedisDB;
use App\Models\StudentCollectionExceptModel;
use App\Libs\DictConstants;
use App\Models\AIPlayRecordModel;
use App\Models\StudentLearnRecordModel;
use App\Models\StudentModel;
use App\Models\StudentSignUpCourseModel;
use App\Libs\Exceptions\RunTimeException;
use App\Models\ReviewCourseModel;
use App\Libs\Util;

class InteractiveClassroomService
{
    //学生上课状态
    const FINISH_LEARNING = 1;      //完成上课
    const MAKE_UP_LESSONS = 2;      //已补课
    const TO_MAKE_UP_LESSONS = 3;   //待补课
    const GO_TO_THE_CLASS = 4;      //去上课
    const LOCK_THE_CLASS = 5;       //未解锁
    const UNLOCK_THE_CLASS = 0;     //已解锁

    //常用时间片段时间戳
    const WEEK_TIMESTAMP = 604800;              //一周
    const HALF_MONTH = 1296000;                 //半个月
    const HALF_HOUR = 1800;                     //半个小时
    const ONE_DAY = 86400;                      //一天

    //集合状态
    const COLLECTION_STATUS_SIGN_UP = 1;        //可报名
    const COLLECTION_STATUS_PRE_ONLINE = 2;     //待上线
    const COLLECTION_STATUS_MAKING = 3;         //制作中

    //学生报名状态
    const COURSE_BIND_STATUS_UNREGISTER = 0;    //未报名
    const COURSE_BIND_STATUS_SUCCESS = 1;       //报名成功
    const COURSE_BIND_STATUS_CANCEL = 2;        //取消报名

    //获取标签信息时区分集合和课程的type
    const OBJECT_COLLECTION = 1;                //集合
    const OBJECT_LESSON = 2;                    //课程

    //tag可见属性
    const TAG_OUTER_ENABLE = 1;                 //tag对外部人员可见

    //Redis缓存集合课程信息
    const CACHE_KEY = "collection_classify_lesson";
    public static $redisDB;


    /**
     * @param $opn
     * @param $studentId
     * @return array
     * 获取推荐课程或可预约课程
     */
    public static function recommendCourse($opn, $studentId)
    {
        $weekNo = date('N',time());
        $collectionList = self::collectionsWithTimeAndTag($opn);
        $collectionIds = array_keys($collectionList);
        $classifyLessonInfo = self::getLessonIds($opn, $collectionIds);
        $learnNumByCollection = StudentLearnRecordModel::learnNumByCollection($studentId);
        $learnNumByCollectionKey = array_column($learnNumByCollection, null, 'collection_id');
        $signUpCollectionIds = self::getSignUpCollections($studentId);

        foreach ($collectionList as $key => $value) {
            if (in_array($value['collection_id'], $signUpCollectionIds)) {
                $collectionList[$key]['course_bind_status'] = self::COURSE_BIND_STATUS_SUCCESS;
                $collectionList[$key]['attend_class_count'] = $learnNumByCollectionKey[$value['collection_id']]['learn_num'] ?? 0;
            } else {
                $collectionList[$key]['course_bind_status'] = self::COURSE_BIND_STATUS_UNREGISTER;
                $collectionList[$key]['attend_class_count'] = 0;
            }
            $startTime = $value['collection_start_time'];
            if ($weekNo < $value['collection_start_week']) {
                $diffDayNum = $value['collection_start_week'] - $weekNo;
                $collectionList[$key]['lesson_start_timestamp'] = strtotime(date("Y-m-d $startTime", strtotime("+$diffDayNum day")));
            } elseif ($weekNo > $value['collection_start_week']) {
                $diffDayNum = 7 - $weekNo + $value['collection_start_week'];
                $collectionList[$key]['lesson_start_timestamp'] = strtotime(date("Y-m-d $startTime", strtotime("+$diffDayNum day")));
            } elseif ($weekNo == $value['collection_start_week']) {
                $collectionList[$key]['lesson_start_timestamp'] = strtotime(date("Y-m-d $startTime", time()));
            }

            $collectionList[$key]['lesson_count'] = isset($classifyLessonInfo[$key]['payLessonList']) ? count($classifyLessonInfo[$key]['payLessonList']) : 0;
        }

        return $collectionList ?? [];
    }

    /**
     * @param $opn
     * @param $page
     * @param $studentId
     * @param int $pageSize
     * @return array|mixed
     * 待上线课程
     */
    public static function preOnlineCourse($opn, $studentId, $page = 1, $pageSize = 100)
    {
        $expectBaseNum = 3000;
        $categoryId = self::erpCategory($opn);
        if (empty($categoryId)) {
            return [];
        }

        $result = $opn->collections($categoryId, $page, $pageSize, self::COLLECTION_STATUS_PRE_ONLINE);
        if (empty($result['data']['list'])){
            return [];
        }
        $collectionIds = array_keys(array_column($result['data']['list'],null,'id'));
        $expectNum = StudentCollectionExceptModel::collectionExpectNum($collectionIds);
        $expectNumByCollection = array_column($expectNum,null,'collection_id');
        $isExceptByStudent = StudentCollectionExceptModel::isExceptByStudent($studentId);
        $eisExceptByCollection = array_column($isExceptByStudent,null,'collection_id');

        foreach ($result['data']['list'] as $key => $value){
            $collectionList[] = [
                'collection_id'   => $value['id'],
                'collection_name' => $value['name'],
                'collection_img'  => $value['cover'],
                'expect_num'      => $expectBaseNum + $expectNumByCollection[$value['id']]['num'],
                'is_expect'       => isset($eisExceptByCollection[$value['id']]),
            ];
        }

        return [
            'total_count' => $result['data']['total_count'],
            'list' => $collectionList ?? [],
        ];
    }

    /**
     * @param $opn
     * @param int $page
     * @param int $pageSize
     * @return array[]
     * 制作中课程
     */
    public static function makingCourse($opn, $page = 1, $pageSize = 100)
    {
        $categoryId = self::erpCategory($opn);
        if (empty($categoryId)) {
            return [];
        }

        $result = $opn->collections($categoryId, $page, $pageSize, self::COLLECTION_STATUS_MAKING);
        if (empty($result['data']['list'])){
            return [];
        }

        foreach ($result['data']['list'] as $key => $value){
            $collectionList[] = [
                'collection_id'   => $value['id'],
                'collection_name' => $value['name'],
                'collection_img'  => $value['cover'],
            ];
        }

        return [
            'total_count' => $result['data']['total_count'],
            'list' => $collectionList ?? [],
        ];
    }

    /**
     * @param $opn
     * @param $collectionIds
     * @return array
     * 根据集合ID获取课程的封面
     */
    public static function getLessonCovers($opn, $collectionIds)
    {
        if (is_array($collectionIds)) {
            $collectionIds = implode(',', $collectionIds);
        }
        $result = $opn->collectionsByIds($collectionIds);
        if (empty($result['data'])) {
            return [];
        }
        foreach ($result['data'] as $value) {
            $data[$value['id']] = $value['img_list'][0] ?? '';
        }
        return $data ?? [];
    }

    /**
     * @param $opn
     * @param $collectionIds
     * @param int $page
     * @param int $pageSize
     * @return array
     * 获取每个集合的课程ID并根据是否付费进行分类
     */
    public static function getLessonIds($opn, $collectionIds, $page = 1, $pageSize = 50)
    {
        if (empty($collectionIds)){
            return [];
        }

        $redis = RedisDB::getConn(self::$redisDB);
        $todayEndTimestamp = strtotime(date('Y-m-d 23:59:59',time()));
        $redis->expireat(self::CACHE_KEY,$todayEndTimestamp);
        $list = $redis->hgetall(self::CACHE_KEY);
        if (!empty($list)) {
            $list = array_map(function ($v) {
                return json_decode($v, true);
            }, $list);
        }

        foreach ($collectionIds as $v) {
            if (array_key_exists($v, $list)) {
                $data[$v] = $list[$v];
                continue;
            }
            $result = $opn->lessons($v, $page, $pageSize);
            foreach ($result['data']['list'] as $value) {
                if ($value['freeflag'] == true) {
                    $data[$v]['freeLessonList'][] = $value['id'];
                } else {
                    $data[$v]['payLessonList'][] = $value['id'];
                }
            }
            $redis->hset(self::CACHE_KEY, $v, json_encode($data[$v] ?? []));
        }
        return $data ?? [];
    }

    /**
     * @param $firstCourseTime
     * @param $timestamp
     * @return bool|int
     * 判断在$endTime这天应该上第几节课
     */
    public static function getSort($firstCourseTime, $timestamp)
    {
        list($beginDay, $endDay) = Util::getStartEndTimestamp($timestamp);
        if ($firstCourseTime >= $beginDay && $firstCourseTime <= $endDay) {
            return 0;
        }

        $startTimeToTimestamp = strtotime(date('Y-m-d', $firstCourseTime));
        $endTimeToTimestamp = strtotime(date('Y-m-d', $timestamp));
        if ($endTimeToTimestamp < $startTimeToTimestamp) {
            return false;
        }
        $diff = ($endTimeToTimestamp - $startTimeToTimestamp) / self::WEEK_TIMESTAMP;
        if (!is_int($diff)) {
            return false;
        }
        return $diff;
    }

    /**
     * @param $courseStartTime
     * @param $date
     * @return int
     * 判断课程应该展示的状态
     */
    public static function getLearnStatus($courseStartTime, $date)
    {
        if (($courseStartTime + self::HALF_HOUR) >= $date) {
            return self::GO_TO_THE_CLASS;
        } else {
            return self::TO_MAKE_UP_LESSONS;
        }
    }

    /**
     * @param $opn
     * @param null $student_id
     * @param $timestamp
     * @param bool $requireSignUp
     * @return \array[][]
     * 获取用户上课计划
     */
    public static function studentCoursePlan($opn, $student_id, $timestamp, $requireSignUp = false)
    {
        list($beginDay, $endDay) = Util::getStartEndTimestamp(time());
        if ($timestamp >= $beginDay) {
            $requireSignUp = true;
        }
        $lastRecord = StudentSignUpCourseModel::getLearnRecords($student_id, $timestamp,$requireSignUp);
        $lastRecordKeyByCollectionId = array_column($lastRecord, null, 'collection_id');
        $lastRecordKeyByLessonId = array_column($lastRecord, null, 'lesson_id');
        if (empty($lastRecordKeyByCollectionId)) {
            return [];
        }

        //获取所有课的详细信息
        $collectionIds = array_unique(array_keys($lastRecordKeyByCollectionId));
        $lessonCoverList = self::getLessonCovers($opn,$collectionIds);
        $lessonListWithCollection = self::getLessonIds($opn, $collectionIds);
        foreach ($lastRecord as $value) {
            $sort = self::getSort($value['first_course_time'], $timestamp);
            if ($sort === false){
                continue;
            }
            $lessonIds[] = $lessonListWithCollection[$value['collection_id']]['payLessonList'][$sort];
        }
        if (empty($lessonIds)) {
            return [];
        }

        if (is_array($lessonIds)){
            $lessonIds = implode(',', $lessonIds);
        }
        $result = $opn->lessonsByIds($lessonIds);
        $lessonList = $result['data'];
        //将记录表中的信息写入的课程信息中
        $time = time();
        foreach ($lessonList as $key => $v) {
            $courseStartTime = (array_search($v['id'], $lessonListWithCollection[$v['collection_id']]['payLessonList'])) * self::WEEK_TIMESTAMP + $lastRecordKeyByCollectionId[$v['collection_id']]['first_course_time'];
            if (isset($lastRecordKeyByLessonId[$v['id']])) {
                $lessonLearnStatus = $lastRecordKeyByLessonId[$v['id']]['learn_status'];
            } else {
                $lessonLearnStatus = self::getLearnStatus($courseStartTime, $time);
            }

            $data[] = [
                'lesson_id'              => $v['id'],
                'lesson_name'            => $v['name'],
                "lesson_start_time"      => date('H:i', $courseStartTime),
                "lesson_learn_status"    => $lessonLearnStatus,
                "collection_id"          => $v['collection_id'],
                "lesson_cover"           => $lessonCoverList[$v['collection_id']] ?? '',
                "lesson_start_timestamp" => $courseStartTime,
            ];
        }

        return $data ?? [];
    }


    /**
     * @param $opn
     * @param int $page
     * @param int $pageSize
     * @return int|mixed
     * 获取互动课堂分类ID（唯一）
     */
    public static function erpCategory($opn, $page = 1, $pageSize = 1)
    {
        $result = $opn->categories($page, $pageSize);
        return $result['data']['list'][0]['id'] ?? 0;
    }

    /**
     * @param $opn
     * @param int $type
     * @param int $page
     * @param int $pageSize
     * @return array
     * 获取互动课堂指定状态集合信息
     */
    public static function erpCollection($opn, $type = self::COLLECTION_STATUS_SIGN_UP, $page = 1, $pageSize = 100)
    {
        $categoryId = self::erpCategory($opn);
        if (empty($categoryId)) {
            return [];
        }

        $result = $opn->collections($categoryId, $page, $pageSize, $type);
        if (empty($result)) {
            return [];
        }
        foreach ($result['data']['list'] as $value) {
            $collectionList[$value['id']] = [
                'collection_id'   => $value['id'],
                'collection_name' => $value['name'],
                'collection_img'  => $value['cover'],
                'lesson_count'    => $value['lesson_count'],
                'type'            => $value['type'],
                'publish_time'    => $value['publish_time'],
            ];
        }
        return $collectionList ?? [];
    }

    /**
     * @param $opn
     * @param $type
     * @param $collectionIds
     * @return array
     * 获取标签信息
     */
    public static function objectTags($opn,$type, $collectionIds)
    {
        $tagsResult = $opn->objectTags($type, $collectionIds);
        if (empty($tagsResult['data'])) {
            return [];
        }
        return $tagsResult['data'] ?? [];
    }

    /**
     * @param $opn
     * @param $collectionIds
     * @return array|mixed
     * 获取集合上课时间信息
     */
    public static function collectionTimetable($opn,$collectionIds)
    {
        $result = $opn->timetable($collectionIds);
        if (empty($result)) {
            return [];
        }
        return $result['data'] ?? [];
    }

    /**
     * @param $opn
     * @return array
     * 集合整合时间和标签
     */
    public static function collectionsWithTimeAndTag($opn)
    {
        $collections = self::erpCollection($opn);
        if (empty($collections)) {
            return [];
        }

        //获取标签信息
        $collectionIds = array_keys($collections);
        $tags = self::objectTags($opn,self::OBJECT_COLLECTION, $collectionIds);
        if (!empty($tags)) {
            foreach ($tags as $key => $value) {
                foreach ($value as $item) {
                    if ($item['outer_view'] == self::TAG_OUTER_ENABLE) {
                        $itemTags[] = $item['name'];
                    }
                }
                $tagList[$key] = $itemTags ?? [];
                unset($itemTags);
            }
        }

        //获取上课时间表
        $timeTable = self::collectionTimetable($opn, $collectionIds);
        $timeTableByCollectionKey = array_column($timeTable, null, 'collection_id');
        $time = time();
        foreach ($collections as $key => $value) {
            $collections[$key]['collection_tags'] = $tagList[$key] ?? [];
            $collections[$key]['collection_start_week'] = $timeTableByCollectionKey[$key]['start_week_day'] ?? 0;
            $collections[$key]['collection_start_time'] = date("H:i", $timeTableByCollectionKey[$key]['start_time']) ?? 0;
            $collections[$key]['is_new'] = ($time - $value['publish_time']) < self::HALF_MONTH;
        }

        return $collections;
    }

    /**
     * @param $studentId
     * @return array
     * 获取用户报名的所有课程
     */
    public static function getSignUpCollections($studentId)
    {
        $where = [
            'student_id'  => $studentId,
            'bind_status' => StudentSignUpCourseModel::COURSE_BING_SUCCESS
        ];
        $signUpCollections = StudentSignUpCourseModel::getRecords($where, ['collection_id']);
        if (empty($signUpCollections)) {
            return [];
        }
        foreach ($signUpCollections as $value) {
            $signUpCollectionIds[] = $value['collection_id'];
        }
        return $signUpCollectionIds ?? [];
    }


    /**
     * @param $opn
     * @param $studentId
     * @return array|array[]
     * 获取平台今明两天的开课计划
     */
    public static function platformCoursePlan($opn, $studentId)
    {
        $time = time();
        //获取所有可报名状态的集合信息
        $todayWeek = date('N', $time);
        $collectionList = self::collectionsWithTimeAndTag($opn);
        if (empty($collectionList)) {
            return [];
        }
        $collectionIds = array_keys($collectionList);
        $classifyLessonInfo = self::getLessonIds($opn, $collectionIds);
        $todayCoursePlan = self::studentCoursePlan($opn, $studentId, $time);
        $todayCoursePlanByCId = array_column($todayCoursePlan, null, 'collection_id');
        $signUpCollections = self::getSignUpCollections($studentId);
        foreach ($collectionList as $key => $value) {
            if ($value['collection_start_week'] == $todayWeek) {
                if (in_array($value['collection_id'], $signUpCollections)) {
                    $value['course_bind_status'] = self::COURSE_BIND_STATUS_SUCCESS;
                    $value['lesson_learn_status'] = (int)$todayCoursePlanByCId[$value['collection_id']]['lesson_learn_status'];
                    $value['lesson_id'] = $todayCoursePlanByCId[$value['collection_id']]['lesson_id'];
                    $value['lesson_start_time'] = $todayCoursePlanByCId[$value['collection_id']]['lesson_start_time'];
                    $value['lesson_start_timestamp'] = $todayCoursePlanByCId[$value['collection_id']]['lesson_start_timestamp'];
                } else {
                    $value['course_bind_status'] = self::COURSE_BIND_STATUS_UNREGISTER;
                }
                $value['lesson_count'] = isset($classifyLessonInfo[$key]['payLessonList']) ? count($classifyLessonInfo[$key]['payLessonList']) : 0;
                $today[] = $value;
            } elseif (($value['collection_start_week'] - 1) == $todayWeek) {
                if (in_array($value['collection_id'], $signUpCollections)) {
                    $value['course_bind_status'] = self::COURSE_BIND_STATUS_SUCCESS;
                } else {
                    $value['course_bind_status'] = self::COURSE_BIND_STATUS_UNREGISTER;
                }
                $value['lesson_count'] = isset($classifyLessonInfo[$key]['payLessonList']) ? count($classifyLessonInfo[$key]['payLessonList']) : 0;
                $tomorrow[] = $value;
            }
        }

        return [
            'today'    => $today ?? [],
            'tomorrow' => $tomorrow ?? [],
            'today_date' => date("m月d日",time()),
            'tomorrow_date' => date("m月d日",strtotime("+1 day")),
        ];
    }

    /**
     * 获取课包详情中的上课状态和开课时间
     * @param $collectionInfo
     * @param $payLessonList
     * @param $recordLearnStatus
     * @param $time
     * @return array
     */
    public static function getLessonStatusAndTimestamp($collectionInfo, $payLessonList, $recordLearnStatus, $time)
    {
        $learnStatus = self::LOCK_THE_CLASS;
        $courseStartTime = 0;
        if ($collectionInfo['signUpStatus'] == self::COURSE_BIND_STATUS_SUCCESS) {
            $courseStartTime = (count($payLessonList) - 1) * self::WEEK_TIMESTAMP + $collectionInfo['firstCourseTime'];
            if (!empty($recordLearnStatus)) {
                return [$recordLearnStatus, $courseStartTime];
            }

            if ($time >= $courseStartTime && $time <= ($courseStartTime + self::HALF_HOUR)) {
                $learnStatus = self::GO_TO_THE_CLASS;
            } elseif ($time > ($courseStartTime + self::HALF_HOUR)) {
                $learnStatus = self::TO_MAKE_UP_LESSONS;
            }
            //当前为取消报名状态
        } elseif ($collectionInfo['signUpStatus'] == self::COURSE_BIND_STATUS_CANCEL) {
            $courseStartTime = (count($payLessonList) - 1) * self::WEEK_TIMESTAMP + $collectionInfo['firstCourseTime'];
            if (!empty($recordLearnStatus)) {
                return [$recordLearnStatus, $courseStartTime];
            }

            if ($courseStartTime >= $collectionInfo['lastLearnTime'] || $courseStartTime > $collectionInfo['subEndDay']) {
                $learnStatus = self::LOCK_THE_CLASS;
            } elseif ($time >= $courseStartTime && $time <= ($courseStartTime + self::HALF_HOUR)) {
                $learnStatus = self::GO_TO_THE_CLASS;
            } elseif ($time > ($courseStartTime + self::HALF_HOUR) && $courseStartTime < $collectionInfo['subEndDay']) {
                $learnStatus = self::TO_MAKE_UP_LESSONS;
            }
        }

        return [$learnStatus, $courseStartTime];
    }

    /**
     * @param $opn
     * @param $collectionId
     * @param $studentId
     * @param int $page
     * @param int $pageSize
     * @return array
     * 课程详情
     */
    public static function collectionDetail($opn, $collectionId, $studentId, $page = 1, $pageSize = 50)
    {
        //获取课包的所有数据
        if (is_array($collectionId)) {
            $collectionId = implode(',', $collectionId);
        }
        $collectionResult = $opn->collectionsByIds($collectionId);
        if (empty($collectionResult['data'])) {
            return [];
        }
        $collection = $collectionResult['data'];

        //整合tags
        $tagsResult = $opn->objectTags(self::OBJECT_COLLECTION, $collectionId);
        if (!empty($tagsResult['data'])) {
            foreach ($tagsResult['data'][$collectionId] as $value) {
                if ($value['outer_view']) {
                    $tags[] = $value['name'];
                }
            }
        }

        //整合课程信息
        $lessonResult = $opn->lessons($collectionId, $page, $pageSize);
        if (empty($lessonResult['data']['list'])){
            return [];
        }
        $collection[0]['lessons'] = $lessonResult['data']['list'];

        //整合课包开课时间
        $timeTableResult = $opn->timetable($collectionId);
        $collection[0]['collection_start_week'] = $timeTableResult['data'][0]['start_week_day'] ?? '';
        $collection[0]['collection_start_time'] = $timeTableResult['data'][0]['start_time'] ?? '';

        //获取报名状态
        $collectionLearnRecord = StudentSignUpCourseModel::getLearnRecordByCollection($collectionId, $studentId);
        $collectionLearnRecordByLessonId = array_column($collectionLearnRecord, null, 'lesson_id');
        if (!empty($collectionLearnRecord)) {
            $collection[0]['collection_bind_status'] = $collectionLearnRecord[0]['bind_status'] ?: self::COURSE_BIND_STATUS_UNREGISTER;
        }else{
            $collection[0]['collection_bind_status'] = self::COURSE_BIND_STATUS_UNREGISTER;
        }

        //判断课程状态
        $time = time();
        $collectionInfo = [
            'signUpStatus'    => $collection[0]['collection_bind_status'],
            'subEndDay'       => strtotime($collectionLearnRecord[0]['sub_end_date'] . " 23:59:59"),
            'firstCourseTime' => $collectionLearnRecord[0]['first_course_time'] ?? 0,
            'lastLearnTime'   => $collectionLearnRecord[0]['update_time'] ?? 0,
        ];
        foreach ($collection[0]['lessons'] as $value) {
            if ($value['freeflag'] == true) {
                $learnStatus = self::UNLOCK_THE_CLASS;
                $freeLessonList[] = $value['lesson_id'];
            } else {
                $payLessonList[] = $value['lesson_id'];
                $recordLearnStatus = $collectionLearnRecordByLessonId[$value['lesson_id']]['learn_status'] ?? '';
                list($learnStatus, $value['lesson_start_timestamp']) = self::getLessonStatusAndTimestamp($collectionInfo, $payLessonList, $recordLearnStatus, $time);
            }
            $lesson[] = [
                'lesson_id'              => $value['lesson_id'],
                'lesson_name'            => $value['lesson_name'],
                'learn_freeflag'         => $value['freeflag'],
                'learn_status'           => $learnStatus,
                'learn_sort'             => $value['sort'],
                'lesson_start_timestamp' => $value['lesson_start_timestamp'] ?? 0,
            ];
        }
        $lessonCount = isset($payLessonList) ? count($payLessonList) : 0;

        foreach ($collection as $value) {
            $result = [
                'collection_id'          => $value['id'],
                'collection_name'        => $value['name'],
                'collection_bind_status' => $value['collection_bind_status'],
                "collection_start_week"  => $value['collection_start_week'],
                "collection_start_time"  => date('H:i', $value['collection_start_time']) ?? "00:00",
                'collection_tags'        => $tags ?? [],
                'collection_cover'       => $value['cover'],
                'collection_desc'        => $value['abstract'],
                'lesson_count'           => $lessonCount ?? 0,
                'lessons'                => $lesson ?? [],
            ];
        }

        return $result ?? [];
    }

    /**
     * @param $opn
     * @param $collectionId
     * @param int $page
     * @param int $pageSize
     * @return array
     * 获取单个课包推荐信息【完课报告分享页】
     */
    public static function erpCollectionByIds($opn, $collectionId, $page = 1, $pageSize = 50)
    {
        if (is_array($collectionId)) {
            $collectionId = implode(',', $collectionId);
        }
        $collectionResult = $opn->collectionsByIds($collectionId);
        if (empty($collectionResult['data'])) {
            return [];
        }
        $collection = $collectionResult['data'];

        //整合课程信息
        $withResources = 1;
        $resourceTypes = 'mp10';
        $lessonResult = $opn->lessons($collectionId, $page, $pageSize,$withResources,$resourceTypes);
        if (!empty($lessonResult['data']['list'])){
            foreach ($lessonResult['data']['list'] as $key => $value){
                if ($value['freeflag'] == true){
                    $lesson = $value;
                    break;
                }
            }
        }

        //整合课包开课时间
        $timeTableResult = $opn->timetable($collectionId);

        $data = [
            'collection_id'         => $collection[0]['id'],
            'collection_name'       => $collection[0]['name'],
            "collection_start_week" => $timeTableResult['data'][0]['start_week_day'] ?? '',
            "collection_start_time" => $timeTableResult['data'][0]['start_time'] ?? '',
            'collection_desc'       => $collection[0]['abstract'],
            'collection_cover'       => $collection[0]['cover'] ?? '',
            'lessons_url'           => $lesson['resources'][0]['resource_url'] ?? '',
        ];
        return $data ?? [];
    }

    /**
     * @param $opn
     * @param $lessonId
     * @return array
     * 获取课程资源信息
     */
    public static function lessonRecourse($opn, $lessonId)
    {
        if (is_array($lessonId)) {
            $lessonId = implode(',', $lessonId);
        }
        $withResources = 1;
        $resourceTypes = 'mp10';
        $lesson = $opn->lessonsByIds($lessonId, $withResources, $resourceTypes);
        return $lesson['data'] ?? [];
    }

    /**
     * @param $studentId
     * @param $collectionId
     * @return array|int|mixed|string|null
     * 期待记录
     */
    public static function expect($studentId, $collectionId)
    {
        $expectRecord = StudentCollectionExceptModel::getRecord(['student_id' => $studentId, 'collection_id' => $collectionId], ['id'], false);
        if (!empty($expectRecord)) {
            return [];
        }
        $insertData = [
            'student_id'    => $studentId,
            'collection_id' => $collectionId,
            'create_time'   => time(),
        ];
        return StudentCollectionExceptModel::insertRecord($insertData, false);
    }

    /**
     * 可报名课程
     * @param $opn
     * @param $studentId
     * @return array
     */
    public static function getSignUpCourse($opn, $studentId)
    {
        $student = StudentModel::getById($studentId);
        if (empty($student)) {
            return [];
        }
        //获取所有可报名课包
        $singUpCourse = self::recommendCourse($opn, $studentId);
//        $singUpCourseData = array_combine(array_column($singUpCourse, 'collection_id'), $singUpCourse);
//        //获取用户购买的课包
//        $studentBingCourse = StudentSignUpCourseModel::getRecords(['student_id'=> $studentId, 'bind_status' => StudentSignUpCourseModel::COURSE_BING_SUCCESS]);
//        $studentBingCourseData = array_combine(array_column($studentBingCourse, 'collection_id'), $studentBingCourse);
//        //统计用户已上课次数
//        $studentLearnCount = StudentLearnRecordModel::getStudentLearnCount($studentId);
//
//        foreach ($singUpCourseData as $key => $value){
//            $value['course_bind_status'] = $studentBingCourseData[$value['collection_id']] ? (INT)$studentBingCourseData[$value['collection_id']]['bind_status'] : StudentSignUpCourseModel::COURSE_BING_ERROR;
//            $value['attend_class_count'] = $studentBingCourseData[$value['collection_id']] ? (INT)$studentLearnCount[$value['collection_id']]['attend_class_count'] : Constants::STATUS_FALSE;
//            $result[] = $value;
//        }
//        return $result ?? [];

        return array_values($singUpCourse) ?? [];
    }


    /**
     * 待发布课程
     * @param $opn
     * @param $studentId
     * @param $page
     * @return array|array[]
     */
    public static function getToBeLaunchedCourse($opn, $studentId, $page)
    {
        $preOnLineCourse = self::preOnlineCourse($opn, $studentId, $page);
        return $preOnLineCourse ?? [];
    }

    /**
     * 获取制作中课程
     * @param $opn
     * @param $page
     * @return array|array[]
     */
    public static function getInProductionCourse($opn, $page)
    {
        $preOnLineCourse = self::makingCourse($opn, $page);
        return $preOnLineCourse ?? [];
    }

    /**
     * 今日练琴计划，显示离当前时间最近的一节课，如果没计划返回今日推荐课程
     * @param $opn
     * @param $studentId
     * @return array|array[]
     */
    public static function getTodayCourse($opn, $studentId)
    {
        $time = time();
        //获取用户今日练琴计划
        $studentTodayCoursePlan = self::studentCoursePlan($opn, $studentId, $time);

        //如果不为空直接返回待上课的一节课程
        if (!empty($studentTodayCoursePlan)) {
            usort($studentTodayCoursePlan, function ($a, $b) {
                return $a['lesson_start_time'] > $b['lesson_start_time'];
            });
            return [$studentTodayCoursePlan[0], []];
        }
        //没有今日上课计划，获取今日推荐课程
        $recommendCourse = self::recommendCourse($opn, $studentId);
        if (empty($recommendCourse)) {
            return [[],[]];
        }

        //判断今天是周几，并且当前课包是否有今天的课
        foreach ($recommendCourse as $item) {
            if ($item['course_bind_status'] != StudentSignUpCourseModel::COURSE_BING_ERROR) {
                continue;
            }
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
     * @param $opn
     * @param $studentId
     * @return array|\array[][]
     */
    public static function getSmallHornInfo($opn, $studentId)
    {
        //获取今明两天的上课计划,如果为空则不做任何处理
        $platformCoursePlan = self::platformCoursePlan($opn, $studentId);
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
        //用户报名当天的课程，下课之前报的名，可以今天上课，下课之后报的名可以下周的这一天上课 上下课以半小时为边界
        $endClassTime = date("H:i", strtotime($startTime) + StudentSignUpCourseModel::DURATION_30MINUTES);
        $nowWeekTime = $nowWeek == $startWeek && date("H:i", $time) > $endClassTime;
        if ($nowWeek == $startWeek && date("H:i", $time) < $endClassTime) {
            $firstCourseTime = strtotime(date('Y-m-d').$startTime);
            $lastTime = $firstCourseTime + Util::TIMESTAMP_ONEDAY * StudentSignUpCourseModel::A_WEEK * ($lessonCount - 1);
        } elseif ($nowWeek > $startWeek || $nowWeekTime) {
            $firstCourseTime = strtotime(date("Y-m-d ", strtotime("+" . StudentSignUpCourseModel::A_WEEK - $nowWeek + $startWeek ."day")) . $startTime);
            $lastTime = $firstCourseTime + Util::TIMESTAMP_ONEDAY * StudentSignUpCourseModel::A_WEEK * ($lessonCount - 1);
        } elseif ($nowWeek < $startWeek) {
            $firstCourseTime = strtotime(date("Y-m-d ", strtotime("+" . $startWeek - $nowWeek ."day")) . $startTime);
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
        if ($student['has_review_course'] != ReviewCourseModel::REVIEW_COURSE_1980) {
            throw new RunTimeException(['please_buy_the_annual_card']);
        }

        $studentLearnRecord = StudentLearnRecordModel::getRecord(['student_id' => $studentId, 'collection_id' => $collectionId, 'lesson_id' => $lessonId]);
        if (!empty($studentLearnRecord)) {
            throw new RunTimeException(['student_learn_record']);
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


        foreach ($studentBindCourse as $item) {
            //如果用户取消当前课包，并且在开课前取消，不做任何处理
            if ($item['bind_status'] == StudentSignUpCourseModel::COURSE_BING_CANCEL && $item['first_course_time'] > $item['update_time']) {
                continue;
            }
            for ($i = 0; $i < $item['lesson_count']; $i++) {
                $last_time = "";
                if (!empty($formatDate)) {
                    $last_time = array_pop($formatDate);
                    array_push($formatDate,$last_time);
                }

                if ($i == 0) {
                    $formatDate[] = date('Ymd', $item['first_course_time']);
                } elseif($i > 0 && $item['bind_status'] == StudentSignUpCourseModel::COURSE_BING_SUCCESS) { //报名状态正常，直接计算用户所有的课程时间
                    $formatDate[] = date('Ymd', strtotime($last_time.'+7day'));
                } elseif ($i > 0 && $item['bind_status'] == StudentSignUpCourseModel::COURSE_BING_CANCEL && strtotime($last_time.'+7day') < $item['update_time']) { //取消报名，只计算取消报名前的课程
                        $formatDate[] = date('Ymd',strtotime($last_time.'+7day'));
                } else {
                    continue;
                }
            }
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
     * @param $opn
     * @param $studentId
     * @param $year
     * @param $month
     * @param $day
     * @return mixed
     */
    public static function getCalendarDetails($opn, $studentId, $year, $month, $day)
    {
        $student = StudentModel::getById($studentId);
        if (empty($student)) {
            return [];
        }

        //没有传时间，默认时间为今天
        if (empty($year) && empty($month) && empty($day)) {
            $date = date('Y-m-d', time());
        } else {
            $date = $year.'-'.$month.'-'.$day;
        }
        $startTime = strtotime($date);
        //今日课程
        $todayClass = self::studentCoursePlan($opn, $studentId, $startTime);
        //如果获取的是今日数据，需要把用户今天的练琴记录返回客户端，客户端实时更新课程状态
        if ($date == date('Y-m-d')) {
            $todayLearn = StudentLearnRecordModel::getRecords(['student_id' => $studentId, 'create_time[>=]' => $startTime]);
        } else{
            $todayLearn = [];
        }

        //如果今日数据为空，并且请求的时间戳大于等于今天时间戳，返回推荐课包信息
        $recommendCourses = [];
        if (empty($todayClass) && strtotime($date) >= strtotime('today')) {
            $recommendCourseData = self::recommendCourse($opn, $studentId);
        } else {
            $recommendCourseData = [];
        }
        //返回今日推荐&未报名的课包
        foreach ($recommendCourseData as $recommendCourse) {
            if ($recommendCourse['collection_start_week'] == date("N", $startTime) && $recommendCourse['course_bind_status'] == StudentSignUpCourseModel::COURSE_BING_ERROR) {
                $recommendCourses[] = $recommendCourse;
            }
        }

        //获取今日练琴时长
        $startTime = strtotime($year . "-" . $month . "-" . $day);
        $endTime = strtotime('+1 day', $startTime) - 1;
        $result['sum_duration'] = AIPlayRecordModel::getStudentSumByDate($studentId, $startTime, $endTime)[0]['sum_duration'];

        //练琴任务
        list($generalTask,$finishTheTask) = CreditService::getActivityList($studentId);
        $result['finish_the_task'] = !empty($generalTask) ? (INT)$finishTheTask : 0;//完成练琴任务次数
        $result['general_task'] = !empty($generalTask) ? $generalTask : 0;//总任务
        $result['today_class'] = $todayClass;
        $result['today_learn'] = $todayLearn;
        $result['recommend_course'] = $recommendCourses;
        $result['date'] = strtotime('today');
        return $result;
    }

    /**
     * 完课报告分享
     * @param $jwt
     * @param $collectionId
     * @param $opn
     * @return array
     * @throws RunTimeException
     */
    public static function shareClassInformation($jwt, $collectionId, $opn)
    {
        $report = [];
        $data = PlayRecordService::parseShareReportToken($jwt);
        if ($data["code" != 0]) {
            throw new RunTimeException(['jwt_invalid']);
        }

        $studentId = $data["student_id"];
        $student = StudentModel::getById($studentId);
        if (empty($student)) {
            throw new RunTimeException(['record_not_found']);
        }

        $channel_id = DictConstants::get(DictConstants::WEIXIN_STUDENT_CONFIG, 'share_class_information_channel_id');
        $playShareAssessUrl = DictConstants::get(DictConstants::APP_CONFIG_STUDENT, 'play_share_assess_url');

        $report['replay_token'] = AIBackendService::genStudentToken($studentId);
        $report['share_token'] = $jwt;
        $TicketData = UserService::getUserQRAliOss($studentId, 1, $channel_id);

        $data = array(
            'ad'=>0,
            'channel_id'=>$channel_id,
            'referee_id'=>$TicketData['qr_ticket']
        );

        $report['play_share_assess_url'] = $playShareAssessUrl.'?'.http_build_query($data);

        //分享课包信息
        $collectionInfo = self::erpCollectionByIds($opn, $collectionId);
        //累计练习天数
        $accumulateDays= AIPlayRecordModel::getAccumulateDays($studentId);

        $report['thumb'] = !empty($student['thumb']) ? AliOSS::signUrls($student['thumb']) : '';
        $report['name'] = $student['name'];
        $report['accumulate_days'] = $accumulateDays;
        $report['collection_name'] = $collectionInfo['collection_name'];
        $report['lessons_url'] = $collectionInfo['lessons_url'];
        $report['collection_cover'] = $collectionInfo['collection_cover'];
        $report['collection_abstract'] = $collectionInfo['collection_desc'];
        $report['start_week'] = $collectionInfo['collection_start_week'];
        $report['start_time'] = $collectionInfo['collection_start_time'];
        return $report;
    }

    /**
     * 获取用户年卡状态
     * @param $studentId
     * @return mixed
     * @throws RunTimeException
     */
    public static function getStudentIdentity($studentId)
    {
        $student = StudentModel::getById($studentId);
        if (empty($student)) {
           throw new RunTimeException(['record_not_found']);
        }

        $appSubStatus = StudentServiceForApp::checkSubStatus($student['sub_status'], $student['sub_end_date']);
        //判断用户是否年卡用户，年卡是否过期
        if ($student['has_review_course'] == ReviewCourseModel::REVIEW_COURSE_1980 && !$appSubStatus) {
            $result['student_status'] = ReviewCourseModel::REVIEW_COURSE_BE_OVERDUE;
        } elseif ($student['has_review_course'] == ReviewCourseModel::REVIEW_COURSE_1980 && $appSubStatus) {
            $result['student_status'] = ReviewCourseModel::REVIEW_COURSE_1980;
        } else {
            $result['student_status'] = (INT)$student['has_review_course'];
        }
        $result['sub_end_time'] = !empty($student['sub_end_date']) ? strtotime($student['sub_end_date'] . "23:59:59") : Constants::STATUS_FALSE;
        return $result;
    }
}