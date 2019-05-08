<?php
/**
 * Created by PhpStorm.
 * User: wangxiong
 * Date: 2018/11/9
 * Time: 下午9:15
 */

namespace App\Services;
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
        $cTime = time();
        // 课程时长
        $cDuration = (int)DictModel::getKeyValue(Constants::COURSE_DURATION, $params['duration']);
        // 课程数据
        $courseData = [
            'name'          => $params['name'],
            'desc'          => $params['desc'],
            'thumb'         => $params['thumb'],
            'duration'      => $cDuration * 60,
            'type'          => $params['course_type'],
            'oprice'        => $params['oprice'] * 100,
            'class_lowest'  => $params['class_lowest'],
            'class_highest' => $params['class_highest'],
            'operator_id'   => $params['operator_id'],
            'num'           => $params['num'],
            'org_id'        => $params['org_id'],
            'status'        => $params['status'],
        ];
        if ($courseId != null) {
            $courseData['update_time'] = $cTime;
            // 编辑课程
            $result = CourseModel::updateCourseRecordById($courseId, $courseData);
        } else {
            $courseData['create_time'] = $cTime;
            $courseData['update_time'] = $cTime;
            // 添加课程
            $result = CourseModel::insertRecord($courseData, false);
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
            $courseUnitList[$k] = CourseModel::courseDataToHumanReadable($v,true);
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
     * 根据courseId, 获取数据
     * @param $courseId
     * @return mixed|null
     */
    public static function getCourseById($courseId)
    {
        return CourseModel::getById($courseId);
    }
}