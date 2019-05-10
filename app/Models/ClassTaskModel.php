<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 18:04
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;

class ClassTaskModel extends Model
{
    public static $table = "class_task";
    public static $redisExpire = 0;
    public static $redisDB;

    const STATUS_CANCEL_AFTER_BEGIN = -1;//开课后取消
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
        if (!empty($params['student_ids'])) {
            $sstuIds = $db->query('select distinct st_id from ' . ClassUserModel::$table . " as stu where stu.user_id in (" . implode(",", $params['student_ids']) . ") and stu.user_role =" . ClassUserModel::USER_ROLE_S)->fetchAll(\PDO::FETCH_COLUMN);
        }
        if (!empty($params['teacher_ids'])) {
            $tstuIds = $db->query('select distinct st_id from ' . ClassUserModel::$table . " as stu where stu.user_id in (" . implode(",", $params['teacher_ids']) . ") and stu.user_role =" . ClassUserModel::USER_ROLE_T)->fetchAll(\PDO::FETCH_COLUMN);

        }
        if (!empty($sstuIds) && !empty($tstuIds)) {
            $where['st.id'] = array_intersect($sstuIds, $tstuIds);
        } else if (!empty($sstuIds)) {
            $where['st.id'] = $sstuIds;
        } else if (!empty($tstuIds)) {
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
    public static function getCTDetail($id, $isOrg = true)
    {
        $where = ['ct.id' => $id];
        if ($isOrg == true) {
            global $orgId;
            if ($orgId > 0)
                $where['ct.org_id'] = $orgId;
        }
        $join = [
            '[><]' . CourseModel::$table . " (c)" => ['ct.course_id' => 'id'],
            '[><]' . ClassroomModel::$table . " (cr)" => ['ct.classroom_id' => 'id'],
        ];

        return MysqlDB::getDB()->get(self::$table . " (ct)", $join, [
            'ct.id',
            'ct.course_id',
            'ct.start_time',
            'ct.end_time',
            'ct.classroom_id',
            'ct.create_time',
            'ct.status',
            'ct.weekday',
            'ct.org_id',
            'c.name (course_name)',
            'c.duration',
            'c.type (course_type)',
            'cr.name (classroom_name)',
            'ct.class_id'
        ], $where);
    }

    /**
     * @param $classId
     * @param null $status
     * @return array
     */
    public static function getCTListByClassId($classId, $status = null)
    {
        $where = ['ct.class_id' => $classId];
        $where['ct.status'] = is_null($status) ? self::STATUS_NORMAL : $status;
        global $orgId;
        if ($orgId > 0)
            $where['ct.org_id'] = $orgId;

        $join = [
            '[><]' . CourseModel::$table . " (c)" => ['ct.course_id' => 'id'],
            '[><]' . ClassroomModel::$table . " (cr)" => ['ct.classroom_id' => 'id'],
        ];

        return MysqlDB::getDB()->select(self::$table . " (ct)", $join, [
            'ct.id',
            'ct.course_id',
            'ct.start_time',
            'ct.end_time',
            'ct.classroom_id',
            'ct.expire_start_date',
            'ct.expire_end_date',
            'ct.create_time',
            'ct.status',
            'ct.weekday',
            'ct.org_id',
            'ct.period',
            'ct.class_id',
            'c.name (course_name)',
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
     * @param $ct
     * @param bool $isOrg
     * @return array
     */
    public static function checkCT($ct,$isOrg = true)
    {
        $db = MysqlDB::getDB();
        $where = [
            'ct.classroom_id' => $ct['classroom_id'],
            'ct.weekday' => $ct['weekday'],
            'ct.status' => array(ClassTaskModel::STATUS_NORMAL, ClassTaskModel::STATUS_TEMP),
            'ct.start_time[<]' => $ct['end_time'],
            'ct.end_time[>]' => $ct['start_time'],
            'ct.org_id' => $ct['org_id'],
            'ct.expire_start_date[<=]' => $ct['expire_end_date'],
            'ct.expire_end_date[>=]' => $ct['expire_start_date'],
        ];
        if (!empty($ct['class_id'])) {
            $where['ct.class_id[!]'] = $ct['class_id'];
        }
        if ($isOrg == true) {
            global $orgId;
            if ($orgId > 0)
                $where['ct.org_id'] = $orgId;
        }
        $join = [
            '[><]' . CourseModel::$table . " (c)" => ['ct.course_id' => 'id'],
            '[><]' . ClassroomModel::$table . " (cr)" => ['ct.classroom_id' => 'id'],
        ];
        $result = $db->select(self::$table . " (ct)", $join, [
            'ct.id',
            'ct.class_id',
            'ct.course_id',
            'ct.start_time',
            'ct.end_time',
            'ct.classroom_id',
            'ct.create_time',
            'ct.status',
            'ct.weekday',
            'ct.org_id',
            'ct.expire_start_date',
            'ct.expire_end_date',
            'c.name (course_name)',
            'cr.name (classroom_name)'

        ], $where);
        return $result;
    }

    public static function updateCTStatus($where,$status,$now = null) {
        if (empty($now)) {
            $now = time();
        }
        return MysqlDB::getDB()->updateGetCount(self::$table, ['status' => $status, 'update_time'=>$now], $where);
    }

    /**
     * @param $userIds
     * @param $userRole
     * @param $start_time
     * @param $end_time
     * @param $weekday
     * @param $expireStartDate
     * @param $expireEndDate
     * @param null $orgClassId
     * @param null $time
     * @param bool $isOrg
     * @return array
     */
    public static function checkUserTime($userIds, $userRole, $start_time, $end_time, $weekday, $expireStartDate, $expireEndDate, $orgClassId = null, $time = null, $isOrg = true)
    {
        $where = [
            'cu.user_id' => $userIds,
            'cu.user_role' => $userRole,
            'ct.expire_start_date[<]' => $expireEndDate,
            'ct.expire_end_date[>]' => $expireStartDate,
            'stc.status' => array(STClassModel::STATUS_NORMAL, STClassModel::STATUS_BEGIN),
            'ct.weekday' => $weekday,
            'ct.start_time[<]' => $end_time,
            'ct.end_time[>]' => $start_time,
            'cu.status' => array(ClassUserModel::STATUS_NORMAL, ClassUserModel::STATUS_BACKUP),
        ];
        if (!empty($orgClassId)) {
            $where['stc.id[!]'] = $orgClassId;
        }
        if ($isOrg == true) {
            global $orgId;
            if ($orgId > 0)
                $where['ct.org_id'] = $orgId;
        }
        $columns = [
            'stc.id',
            'cu.user_id',
            'cu.user_role',
            'ct.classroom_id',
        ];

        $join = [
            '[><]' . STClassModel::$table . ' (stc)' => ['ct.class_id' => 'id'],
            '[><]' . ClassUserModel::$table . ' (cu)' => ['stc.id' => 'class_id'],
        ];

        return MysqlDB::getDB()->select(self::$table . ' (ct)', $join, $columns, $where);
    }
}