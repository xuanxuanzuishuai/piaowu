<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 18:04
 */

namespace App\Models;


use App\Libs\MysqlDB;
use Medoo\Medoo;

class ScheduleTaskModel extends Model
{
    public static $table = "schedule_task";
    public static $redisExpire = 0;
    public static $redisDB;

    const STATUS_CANCEL_AFTER_BEGIN= -1;//开课后取消
    const STATUS_CANCEL = 0;//取消排课
    const STATUS_NORMAL = 1;//正常排课
    const STATUS_BEGIN = 2;//开课
    const STATUS_END = 3;//结课
    const STATUS_TEMP = 4;//临时调课
    const STATUS_UNFULL = 5; // 未满员
    const STATUS_FULL = 6; // 已满员

    /**
     * @param $params
     * @param int $page
     * @param int $count
     * @param bool $isOrg
     * @return array
     */
    public static function getSTList($params, $page = -1, $count = 20, $isOrg = true)
    {
        $db = MysqlDB::getDB();
        $where = [];
        if (is_numeric($params['classroom_id'])) {
            $where['st.classroom_id'] = $params['classroom_id'];
        }
        if (is_numeric($params['course_id'])) {
            $where['st.course_id'] = $params['course_id'];
        }
        if (is_numeric($params['status'])) {
            $where['st.status'] = $params['status'];
        }

        if (is_numeric($params['weekday'])) {
            $where['st.weekday'] = $params['weekday'];
        }
        $sstuIds = $tstuIds = [];
        if(!empty($params['student_ids'])) {
            $sstuIds = $db->query('select distinct st_id from '.ScheduleTaskUserModel::$table." as stu where stu.user_id in (".implode(",",$params['student_ids']).") and stu.user_role =".ScheduleTaskUserModel::USER_ROLE_S)->fetchAll(\PDO::FETCH_COLUMN);
        }
        if(!empty($params['teacher_ids'])) {
            $tstuIds = $db->query('select distinct st_id from '.ScheduleTaskUserModel::$table." as stu where stu.user_id in (".implode(",",$params['teacher_ids']).") and stu.user_role =".ScheduleTaskUserModel::USER_ROLE_T)->fetchAll(\PDO::FETCH_COLUMN);

        }
        if(!empty($sstuIds) && !empty($tstuIds)) {
            $where['st.id'] = array_intersect($sstuIds,$tstuIds);
        }
        else if(!empty($sstuIds)) {
            $where['st.id'] = $sstuIds;
        } else if(!empty($tstuIds)) {
            $where['st.id'] = $tstuIds;
        }
        if ($isOrg == true) {
            global $orgId;
            if ($orgId > 0)
                $where['st.org_id'] = $orgId;
        }
        $totalCount = 0;
        if ($page != -1) {

            // 获取总数
            $totalCount = $db->count(self::$table . " (st)", "*", $where);
            // 分页设置
            $where['LIMIT'] = [($page - 1) * $count, $count];
        }
        // 排序设置
        $where['ORDER'] = [
            'st.weekday' => 'ASC',
            'st.start_time' => 'ASC'
        ];
        $join = [
            '[><]' . CourseModel::$table . " (c)" => ['st.course_id' => 'id'],
            '[><]' . ClassroomModel::$table . " (cr)" => ['st.classroom_id' => 'id'],
        ];
        $result = $db->select(self::$table . " (st)", $join, [
            'st.id',
            'st.course_id',
            'st.start_time',
            'st.end_time',
            'st.classroom_id',
            'st.create_time',
            'st.status',
            'st.weekday',
            'st.org_id',
            'st.expire_start_date',
            'st.expire_end_date',
            'st.real_schedule_id',
            'c.name (course_name)',
            'c.class_highest',
            'c.type (course_type)',
            'cr.name (classroom_name)'

        ], $where);
        return array($totalCount, $result);
    }

    /**
     * @param $id
     * @param bool $isOrg
     * @return array
     */
    public static function getSTDetail($id, $isOrg = true)
    {
        $where = ['st.id' => $id];
        if ($isOrg == true) {
            global $orgId;
            if ($orgId > 0)
                $where['st.org_id'] = $orgId;
        }
        $join = [
            '[><]' . CourseModel::$table . " (c)" => ['st.course_id' => 'id'],
            '[><]' . ClassroomModel::$table . " (cr)" => ['st.classroom_id' => 'id'],
        ];

        return MysqlDB::getDB()->get(self::$table . " (st)", $join, [
            'st.id',
            'st.course_id',
            'st.start_time',
            'st.end_time',
            'st.classroom_id',
            'st.create_time',
            'st.status',
            'st.weekday',
            'st.org_id',
            'st.expire_start_date',
            'st.expire_end_date',
            'st.real_schedule_id',
            'c.name (course_name)',
            'c.class_highest',
            'c.num',
            'cr.campus_id',
            'c.duration',
            'c.type (course_type)',
            'cr.name (classroom_name)'
        ], $where);
    }

    /**
     * @param $insert
     * @return int|mixed|string|null
     */
    public static function addST($insert)
    {
        return self::insertRecord($insert);
    }

    /**
     * @param $st
     * @return bool
     */
    public static function modifyST($st)
    {
        $result = self::updateRecord($st['id'], $st);
        return ($result && $result > 0);
    }


    /**
     * @param $userIds
     * @param $userRole
     * @param $start_time
     * @param $end_time
     * @param $weekday
     * @param $expireStartDate
     * @param null $orgSTId
     * @param null $time
     * @param bool $isOrg
     * @return array
     */
    public static function getSTListByUser($userIds, $userRole, $start_time, $end_time, $weekday, $expireStartDate, $orgSTId = null, $time = null, $isOrg = true)
    {
        $where = [

            'stu.user_id' => $userIds,
            'stu.user_role' => $userRole,
            'AND' => [
                'st.expire_start_date[<=]' => $expireStartDate,
                'OR' => [
                    'st.expire_end_date[>=]' => $expireStartDate,
                    'expire_end_date' => '0000-00-00',
                ]
            ],
            'st.status' => array(ScheduleTaskModel::STATUS_NORMAL, ScheduleTaskModel::STATUS_BEGIN, ScheduleTaskModel::STATUS_TEMP),
            'st.weekday' => $weekday,
            'st.start_time[<]' => $end_time,
            'st.end_time[>]' => $start_time,
            'stu.status' => array(ScheduleTaskUserModel::STATUS_NORMAL, ScheduleTaskUserModel::STATUS_BACKUP),
        ];
        if (!empty($orgSTId)) {
            $where['st.id[!]'] = $orgSTId;
        }
        if ($isOrg == true) {
            global $orgId;
            if ($orgId > 0)
                $where['st.org_id'] = $orgId;
        }
        $columns = [
            'st.id',
            'stu.user_id',
            'stu.user_role',
            'st.classroom_id',
            'st.real_schedule_id',
        ];

        $join = [
            '[><]' . ScheduleTaskUserModel::$table . ' (stu)' => ['st.id' => 'st_id'],
        ];

        return MysqlDB::getDB()->select(self::$table . ' (st)', $join, $columns, $where);
    }

    /**
     * @param $st
     * @param bool $isOrg
     * @return array
     */
    public static function checkSTList($st,$isOrg = true)
    {
        $db = MysqlDB::getDB();
        $where = [

            'st.classroom_id' => $st['classroom_id'],
            'st.weekday' => $st['weekday'],
            'st.status' => array(ScheduleTaskModel::STATUS_NORMAL, ScheduleTaskModel::STATUS_BEGIN, ScheduleTaskModel::STATUS_TEMP),
            'st.start_time[<]' => $st['end_time'],
            'st.end_time[>]' => $st['start_time'],
            'st.org_id' => $st['org_id'],
            'AND' => [
                'st.expire_start_date[<=]' => $st['expire_start_date'],
                'OR' => [
                    'st.expire_end_date[>=]' => $st['expire_start_date'],
                    'expire_end_date' => '0000-00-00',
                ]
            ]
        ];
        if (!empty($st['id'])) {
            $where['st.id[!]'] = $st['id'];
        }
        if ($isOrg == true) {
            global $orgId;
            if ($orgId > 0)
                $where['st.org_id'] = $orgId;
        }
        $join = [
            '[><]' . CourseModel::$table . " (c)" => ['st.course_id' => 'id'],
            '[><]' . ClassroomModel::$table . " (cr)" => ['st.classroom_id' => 'id'],
        ];
        $result = $db->select(self::$table . " (st)", $join, [
            'st.id',
            'st.course_id',
            'st.start_time',
            'st.end_time',
            'st.classroom_id',
            'st.create_time',
            'st.status',
            'st.weekday',
            'st.org_id',
            'st.expire_start_date',
            'st.expire_end_date',
            'st.real_schedule_id',
            'c.name (course_name)',
            'cr.name (classroom_name)'

        ], $where);
        return $result;

    }
}