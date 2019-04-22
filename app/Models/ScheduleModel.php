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
            's.type',
            's.st_id',
            's.org_id',
            'c.name (course_name)',
            'c.duration',
            'cr.name (classroom_name)'
        ], $where);
    }

}