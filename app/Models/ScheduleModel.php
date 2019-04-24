<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-22
 * Time: 16:04
 */

namespace App\Models;


use App\Libs\MysqlDB;

class ScheduleModel extends Model
{
    public static $table = "schedule";
    /** 课程状态 */
    const STATUS_BOOK = 1;            //预约成功
    const STATUS_IN_CLASS = 2;        //上课中
    const STATUS_FINISH = -1;         //下课
    const STATUS_CANCEL = -2;         //课程取消

    const TYPE_EXPERIENCE = 1;
    const TYPE_NORMAL = 2;

    /**
     * @param $schedule
     * @return int|mixed|string|null
     */
    public static function insertSchedule($schedule)
    {
        return self::insertRecord($schedule);
    }

    /**
     * @param $id
     * @param bool $isOrg
     * @return mixed
     */
    public static function getDetail($id, $isOrg = true)
    {
        $where = ['s.id' => $id];
        if ($isOrg == true) {
            global $orgId;
            if ($orgId > 0)
                $where['s.org_id'] = $orgId;
        }
        $join = [
            '[><]' . CourseModel::$table . " (c)" => ['s.course_id' => 'id'],
            '[><]' . ClassroomModel::$table . " (cr)" => ['s.classroom_id' => 'id'],
        ];

        return MysqlDB::getDB()->get(self::$table . " (s)", $join, [
            's.id',
            's.course_id',
            's.start_time',
            's.end_time',
            's.classroom_id',
            's.create_time',
            's.status',
            's.st_id',
            's.org_id',
            'c.name (course_name)',
            'c.type (course_type)',
            'c.duration',
            'cr.name (classroom_name)',
            'cr.campus_id'
        ], $where);
    }

    /**
     * @param $params
     * @param int $page
     * @param int $count
     * @param bool $isOrg
     * @return array
     */
    public static function getList($params, $page = -1, $count = 20, $isOrg = true)
    {
        $db = MysqlDB::getDB();
        $where = [];
        if (!empty($params['classroom_id'])) {
            $where['s.classroom_id'] = $params['classroom_id'];
        }
        if (!empty($params['course_id'])) {
            $where['s.course_id'] = $params['course_id'];
        }
        if (isset($params['status'])) {
            $where['s.status'] = $params['status'];
        }
        if (!empty($params['s_time_start'])) {
            $where['s.start_time[>=]'] = strtotime($params['s_time_start']);
        }
        if (!empty($params['s_time_end'])) {
            $where['s.start_time[<]'] = strtotime($params['s_time_end']);
        }
        if (!empty($params['st_id'])) {
            $where['s.st_id'] = $params['st_id'];
        }
        if ($isOrg == true) {
            global $orgId;
            if ($orgId > 0)
                $where['s.org_id'] = $orgId;
        }
        $totalCount = 0;
        if ($page != -1) {
            // 获取总数
            $totalCount = $db->count(self::$table . " (s)", "*", $where);
            // 分页设置
            $where['LIMIT'] = [($page - 1) * $count, $count];
        }
        // 排序设置
        $where['ORDER'] = [
            'start_time' => 'DESC'
        ];
        $join = [
            '[><]' . CourseModel::$table . " (c)" => ['s.course_id' => 'id'],
            '[><]' . ClassroomModel::$table . " (cr)" => ['s.classroom_id' => 'id'],
        ];
        $result = $db->select(self::$table . " (s)", $join, [
            's.id',
            's.course_id',
            's.start_time',
            's.end_time',
            's.classroom_id',
            's.create_time',
            's.status',
            's.org_id',
            's.st_id',
            'c.type (course_type)',
            'c.name (course_name)',
            'cr.campus_id',
            'cr.name (classroom_name)'

        ], $where);
        return array($totalCount, $result);
    }

    /**
     * @param $schedule
     * @return array
     */
    public static function checkSchedule($schedule)
    {
        $db = MysqlDB::getDB();
        $where = [
            's.classroom_id' => $schedule['classroom_id'],
            's.status' => array(self::STATUS_BOOK, self::STATUS_IN_CLASS),
            's.start_time[<]' => $schedule['end_time'],
            's.end_time[>]' => $schedule['start_time'],
            's.org_id' => $schedule['org_id'],
        ];

        $join = [
            '[><]' . CourseModel::$table . " (c)" => ['s.course_id' => 'id'],
            '[><]' . ClassroomModel::$table . " (cr)" => ['s.classroom_id' => 'id'],
        ];
        $result = $db->select(self::$table . " (s)", $join, [
            's.id',
            's.course_id',
            's.start_time',
            's.end_time',
            's.classroom_id',
            's.create_time',
            's.status',
            's.org_id',
            'c.type (course_type)',
            'c.name (course_name)',
            'cr.name (classroom_name)',
        ], $where);
        return $result;
    }

    /**
     * @param $schedule
     * @return bool
     */
    public static function modifySchedule($schedule)
    {
        $result = self::updateRecord($schedule['id'], $schedule);
        return ($result && $result > 0);
    }

    public static function modifyScheduleBySTId($data,$where) {
        $result = self::batchUpdateRecord($data,$where);
            return ($result && $result > 0);
    }

}