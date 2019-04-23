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

    public static function insertSchedule($schedule) {
        return self::insertRecord($schedule);
    }

    public static function getDetail($id,$isOrg = true) {
        $where = ['s.id' => $id];
        if ($isOrg == true) {
            global $orgId;
            if ($orgId > 0)
                $where['s.org_id'] = $orgId;
        }
        $join = [
            '[><]' . CourseModel::$table . " (c)" => ['s.course_id'=>'id'],
            '[><]' . ClassroomModel::$table . " (cr)" => ['s.classroom_id'=>'id'],
        ];

        return MysqlDB::getDB()->get(self::$table." (s)", $join, [
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
            'cr.name (classroom_name)'
        ], $where);
    }

    public static function getList($params,$page = -1,$count = 20,$isOrg = true) {
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
        if ($isOrg == true) {
            global $orgId;
            if ($orgId > 0)
                $where['s.org_id'] = $orgId;
        }
        $totalCount = 0;
        if ($page != -1) {
            // 获取总数
            $totalCount = $db->count(self::$table." (s)", "*", $where);
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
            'c.type (course_type)',
            'c.name (course_name)',
            'cr.name (classroom_name)'

        ], $where);
        return array($totalCount, $result);
    }

}