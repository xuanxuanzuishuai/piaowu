<?php
/**
 * Created by PhpStorm.
 * User: wangxiong
 * Date: 2018/11/9
 * Time: 下午9:15
 */

namespace App\Services\Product;
use App\Libs\Constants;
use App\Libs\Dict;
use App\Libs\Valid;
use App\Models\CourseModel;
use App\Models\AppModel;
use App\Models\DictModel;


class CourseService
{

    /**
     * 添加或编辑课程
     * @author wangxiong
     * @param $params
     * @param null $courseId
     * @return array|int|mixed|null|string
     */
    public static function addOrEditCourse($params, $courseId = null)
    {
        $appInfo = AppModel::getSingleRecord($params['product_line']);
        if (empty($appInfo)) {
            return Valid::addErrors([], 'product_line', 'product_line_not_exists');
        }

        $cTime = time();
        // 课程时长
        $cDuration = (int)DictModel::getKeyValue(Constants::COURSE_DURATION, $params['duration']);
        // 课程数据
        $courseData = [
            'name'          => $params['name'],
            'desc'          => $params['desc'],
            'thumb'         => $params['thumb'],
            'app_id'        => $params['product_line'],
            'duration'      => $cDuration * 60,
            'type'          => $params['course_type'],
            'level'         => $params['level'],
            'oprice'        => $params['oprice'] * 100,
            'class_lowest'  => $params['class_lowest'],
            'class_highest' => $params['class_highest'],
            'operator_id'   => $params['operator_id'],
        ];
        if ($courseId != null) {
            $courseData['update_time'] = $cTime;
            // 编辑课程
            $result = CourseModel::updateCourseRecordById($courseId, $courseData);
        } else {
            $courseData['create_time'] = $cTime;
            $courseData['update_time'] = $cTime;
            $courseData['status'] = CourseModel::COURSE_STATUS_WAIT;
            // 添加课程
            $result = CourseModel::insertRecord($courseData);
        }
        // 错误处理
        if($result == null) {
            // 数据插入或更新失败，请检查数据是否重复！
            return Valid::addErrors([], 'product_line', 'info_insert_or_update_error_check_duplicate');
        }
        return $result;
    }


    /**
     * 获取课程列表数据
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function getCourseUnitList($page, $count, $params)
    {
        // 获取数据
        list($totalCount, $courseUnitList) = CourseModel::getCourseListByFilter($page, $count, $params);

        // 数据处理
        foreach ($courseUnitList as $k => $v) {
            $courseUnitList[$k] = CourseModel::courseDataToHumanReadable($v);
        }
        return [$totalCount, $courseUnitList] ;
    }


    /**
     * 获取课程详情
     * @param $courseId
     * @return array|mixed
     */
    public static function getCourseDetailById ($courseId)
    {
        if (empty($courseId)) return [];
        // 获取数据
        $courseDetail = CourseModel::getCourseUnitDetailById($courseId);
        if (empty($courseDetail)) return [];
        // 数据处理
        $courseDetail = CourseModel::courseDataToHumanReadable($courseDetail, false);
        return $courseDetail ?? [];
    }

    /**
     * 获取课程
     * @param $courseId
     * @return array
     */
    public static function selectCourseByCourseId($courseId) {
        $course = CourseModel::getLiteInfo($courseId);
        if ($course) {
            //查询结果已经包含course_id这个key
            $course['num']         = 1;
            $course['free_num']    = 0;
            $course['sprice']      = $course['oprice'];
            $course['course_name'] = $course['name'];
            unset($course['oprice'], $course['name']);
            return [$course];
        }
        return [];
    }

    /**
     * 根据courseId, 获取数据
     * @param $courseId
     * @return mixed|null
     */
    public static function getCourseById($courseId)
    {
        return CourseModel::getById($courseId);
    }

    /**
     * 获取体验课course_id
     * @param $appId
     * @return bool|mixed
     */
    public static function getTestCourseId($appId)
    {
        switch($appId) {
            case AppModel::APP_PANDA:
                $keyCode = Constants::DICT_KEY_PANDA_NORMAL_REGISTER;
                break;
            case AppModel::APP_SQUIRREL:
                $keyCode = Constants::DICT_KEY_SQUIRREL_NORMAL_REGISTER;
                break;
            default:
                $keyCode = '';
        }
        if (empty($keyCode)) {
            return false;
        }
        return Dict::getCourseId($keyCode);
    }

    /**
     * 获取设备课course_id
     * @param $appId
     * @return mixed
     */
    public static function getDeviceCourseId($appId)
    {
        switch($appId) {
            case AppModel::APP_PANDA:
                $keyCode = Constants::DICT_KEY_PANDA_DEVICE_COURSE_ID;
                break;
            case AppModel::APP_SQUIRREL:
                $keyCode = Constants::DICT_KEY_SQUIRREL_DEVICE_COURSE_ID;
                break;
            default:
                $keyCode = '';
        }
        if (empty($keyCode)) {
            return false;
        }
        return Dict::getCourseId($keyCode);
    }

    /**
     * 磨课、种子用户考核课
     * @return array
     */
    public static function getSpecialCourse()
    {
        $types = [
            CourseModel::TYPE_INTERVIEW,
            CourseModel::TYPE_EXAMINE
        ];
        return CourseModel::getCoursesByType($types);
    }

    /**
     * 保存一个course
     * @param $data
     * @return int|mixed|null|string
     */
    public static function saveCourse($data){
        return CourseModel::insertRecord($data);
    }

    /**
     * 更新指定id的course
     * @param $courseId
     * @param $data
     * @return int|null
     */
    public static function updateCourseRecordById($courseId,$data){
        return CourseModel::updateCourseRecordById($courseId,$data);
    }
}