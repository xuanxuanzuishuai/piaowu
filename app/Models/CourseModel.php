<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2018/11/8
 * Time: 下午4:16
 */

namespace App\Models;
use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\Util;


class CourseModel extends Model
{
    public static $table = "course";

    /** 课程类型 */
    const TYPE_TEST = 1;         // 体验课
    const TYPE_NORMAL = 2;       // 正式课
    const TYPE_HARDWARE = 4;     // 智能硬件（灯条）
    const TYPE_DEVICE = 31;      // 设备测试课
    const TYPE_INTERVIEW = 32;   // 老师磨课，培训师考核课
    const TYPE_EXAMINE = 33;     // 老师考核，种子用户考核课
    const TYPE_TRAINING = 34;    // 老师培训课，老师使用学生端

    // 课程状态 -1 待发布 0 停用（不可用） 1 正常
    const COURSE_STATUS_WAIT   = -1;
    const COURSE_STATUS_STOP   = 0;
    const COURSE_STATUS_NORMAL = 1;

    // 课程时长
    const DURATION_50MINUTES = 3000; // 50分钟
    const DURATION_25MINUTES = 1500; // 25分钟
    const DURATION_30MINUTES = 1800; // 30分钟

    /**
     * 获取课程列表
     * @param $page
     * @param $count
     * @param $params
     * @param bool $isOrg
     * @return array
     */
    public static function getCourseListByFilter($page = -1, $count, $params,$isOrg = true) {
        $where = [];
        $db = MysqlDB::getDB();

        $join = [
            '[><]' . EmployeeModel::$table . "(e)" => ['c.operator_id' => 'id']
        ];

        if (!empty($params['course_type'])) {
            $where['AND']['c.type'] = $params['course_type'];
        }
        if (!empty($params['level'])) {
            $where['AND']['c.level'] = $params['level'];
        }
        if (!empty($params['duration'])) {
            $cDuration = (int)DictModel::getKeyValue(Constants::COURSE_DURATION, $params['duration']);
            $where['AND']['c.duration'] = $cDuration * 60;
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $where['AND']['c.status'] = $params['status'];
        }
        if($isOrg == true) {
            global $orgId;
            if($orgId > 0 )
                $where['c.org_id'] = $orgId;
        }
        $columns = [
            "c.id(course_id)",
            "c.name",
            "c.type(course_type)",
            "c.duration",
            "c.level",
            "c.oprice",
            "c.class_lowest",
            "c.class_highest",
            "c.desc",
            "c.status",
            "c.operator_id",
            "c.create_time",
            "c.update_time",
            "c.thumb",
            "e.name(operator_name)",
            "c.num",
        ];

        // 获取总数
        $totalCount = $db->count(self::$table . "(c)", $join, "*", $where);
        // 分页设置
        if($page != -1) {
            $where['LIMIT'] = [($page - 1) * $count, $count];
        }
        // 排序设置
        $where['ORDER'] = [
            'c.update_time' => 'DESC'
        ];

        // 获取数据
        $courseUnitList = $db->select(self::$table . "(c)",
            $join,
            $columns,
            $where
        );

        return [$totalCount, $courseUnitList];
    }

    /**
     * 获取课程单元详情
     * @param $courseId
     * @return array|mixed
     */
    public static function getCourseUnitDetailById($courseId) {
        $db = MysqlDB::getDB();
        $where['c.id'] = $courseId;

        // 获取数据
        $courseUnitList = $db->get(self::$table ."(c)",
            [
                "c.id(course_id)",
                "c.name",
                "c.type(course_type)",
                "c.duration",
                "c.level",
                "c.oprice",
                "c.class_lowest",
                "c.class_highest",
                "c.desc",
                "c.thumb",
                "c.status",
                "c.create_time",
                "c.num",
            ],
            $where
        );
        return $courseUnitList ?? [];
    }

    /**
     * 数据可读
     * @author wangxiong
     * @param $params
     * @param bool $isTranslate
     * @return array
     */
    public static function courseDataToHumanReadable($params, $isTranslate = true) {
//        $isTranslate && $params['product_line'] = AppModel::getAppName($params['product_line']);
//        $isTranslate && $params['instrument'] = DictModel::getKeyValue(Constants::DICT_TYPE_INSTRUMENT, $params['instrument']);
        $isTranslate && $params['course_type'] = DictModel::getKeyValue(Constants::DICT_COURSE_TYPE, $params['course_type']);
        $isTranslate && $params['status'] = DictModel::getKeyValue(Constants::DICT_COURSE_STATUS, $params['status']);
        $isTranslate && $params['level'] = DictModel::getKeyValue(Constants::DICT_COURSE_LEVEL, $params['level']);
        $params['duration'] = ($params['duration'] / 60).'min';
        $params['class_type'] = $params['class_lowest'].'-'.$params['class_highest'];
        $params['oprice'] = number_format($params['oprice'] / 100, 2);
        $isTranslate && !empty($params['thumb']) && $params['thumb'] = Util::getQiNiuFullImgUrl($params['thumb']);
        return $params ?? [];
    }

    /**
     * 查询一个课程的简化信息
     * @author zjh
     * @param $courseId
     * @return mixed
     */
    public static function getLiteInfo($courseId){
        $db = MysqlDB::getDB();
        return $db->get(self::$table,['id(course_id)','oprice','name'],['id' => $courseId]);
    }

    /**
     * 获取课程(ID和名称)
     * @param bool $isNormalStatus
     * @return array
     */
    public static function getAllCourseList($isNormalStatus = true) {
        $db = MysqlDB::getDB();
        $where = [];
        $isNormalStatus && $where = ['status' => self::COURSE_STATUS_NORMAL];
        // 获取数据
        $courseUnitList = $db->select(self::$table."(c)",
            [
                "c.id(course_id)",
                "c.name",
            ],
            $where
        );
        return $courseUnitList ?? [];
    }

    /**
     * 编辑课程
     * @param $courseId
     * @param $updateData
     * @return int|null
     */
    public static function updateCourseRecordById($courseId, $updateData) {
        return self::updateRecord($courseId,$updateData);
    }

    /**
     * 根据课程类型，获取课程
     * @param $types
     * @return array
     */
    public static function getCoursesByType($types)
    {
        return self::getRecords([
            self::$table . '.status' => self::COURSE_STATUS_NORMAL,
            self::$table . '.type' => $types
        ],[
            self::$table . '.id(course_id)',
            self::$table . '.name',
            self::$table . '.duration',
            self::$table . '.app_id',
            self::$table . '.type'
        ]);
    }

}