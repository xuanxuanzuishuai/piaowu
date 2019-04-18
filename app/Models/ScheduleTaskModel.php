<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 18:04
 */

namespace App\Models;


use App\Controllers\Schedule\ScheduleTaskUser;
use App\Libs\MysqlDB;

class ScheduleTaskModel extends Model
{
    public static $table = "schedule_task";
    public static $redisExpire = 0;
    public static $redisDB;

    const STATUS_CANCEL = 0;//取消排课
    const STATUS_NORMAL = 1;//正常排课
    const STATUS_BEGIN = 2;//开课
    const STATUS_END = 3;//结课
    const STATUS_TEMP = 4;//临时调课

    /**
     * @param $params
     * @param int $page
     * @param int $count
     * @param bool $isOrg
     * @return array
     */
    public static function getSTList($params, $page = -1, $count = 20,$isOrg = true)
    {
        $db = MysqlDB::getDB();
        $where = [];
        if (!empty($params['classroom_id'])) {
            $where['classroom_id'] = $params['classroom_id'];
        }
        if (!empty($params['course_id'])) {
            $where['course_id'] = $params['course_id'];
        }
        if (isset($params['status'])) {
            $where['status'] = $params['status'];
        }
        if (isset($params['weekday'])) {
            $where['weekday'] = $params['weekday'];
        }
        if($isOrg == true) {
            global $orgId;
            if($orgId > 0 )
                $where['org_id'] = $orgId;
        }
        $totalCount = 0;
        if ($page != -1) {
            // 获取总数
            $totalCount = $db->count(self::$table, "*", $where);
            // 分页设置
            $where['LIMIT'] = [($page - 1) * $count, $count];
        }
        // 排序设置
        $where['ORDER'] = [
            'relation_id' => 'ASC',
            'weekday' => 'ASC',
            'start_time' => 'ASC'
        ];
        $join = [
            '[>]'.CourseModel::$table." (c)" => ['c.id'=>'course_id'],
            '[>]'.ClassroomModel::$table." (cr)" => ['cr.id'=>'classroom_id'],
        ];
        $result = $db->select(self::$table." (st)", $join,[
            'st.id',
            'st.course_id',
            'st.start_time',
            'st.end_time',
            'st.classroom_id',
            'st.create_time',
            'st.status',
            'st.weekday',
            'st.org_id',
            'st.expire_time',
            'st.real_schedule_id',
            'c.name (course_name)',
            'cr.name (classroom_name)'

        ], $where);
        return array($totalCount, $result);
    }

    /**
     * @param $id
     * @return array
     */
    public static function getSTDetail($id)
    {
        $join = [
            '[>]'.CourseModel::$table." (c)" => ['c.id'=>'course_id'],
            '[>]'.ClassroomModel::$table." (cr)" => ['cr.id'=>'classroom_id'],
        ];

        return MysqlDB::getDB()->select(self::$table, $join,[
            'st.id',
            'st.course_id',
            'st.start_time',
            'st.end_time',
            'st.classroom_id',
            'st.create_time',
            'st.status',
            'st.weekday',
            'st.org_id',
            'st.expire_time',
            'st.real_schedule_id',
            'c.name (course_name)',
            'cr.name (classroom_name)'
        ], ['id' => $id]);
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
     * @param $ids
     * @param $update
     * @return bool
     */
    public static function modifyST($ids, $update)
    {
        $result = self::updateRecord($ids, $update);
        return ($result && $result > 0);
    }


    /**
     * @param $userIds
     * @param $userRole
     * @param null $time
     * @param bool $isOrg
     * @return array
     */
    public static function getSTListByUser($userIds, $userRole, $time = null, $isOrg = true)
    {
        $time = empty($time) ?? time();
        $where = [
            'AND' =>
            [
                'stu.user_id' => $userIds,
                'stu.user_role' => $userRole,
                'or' => [
                    'expire_time' => null,
                    'expire_time[>]' => $time
                ],
                'st.status' => array(ScheduleTaskModel::STATUS_NORMAL, ScheduleTaskModel::STATUS_BEGIN, ScheduleTaskModel::STATUS_TEMP),
                'stu.status' => array(ScheduleTaskUserModel::STATUS_NORMAL,ScheduleTaskUserModel::STATUS_BACKUP),
            ]
        ];

        if($isOrg == true) {
            global $orgId;
            if($orgId > 0 )
                $where['org_id'] = $orgId;
        }
        $columns = [
            'st.id',
            'stu.user_id',
            'stu.user_role',
            'st.classroom_id',
            'st.real_schedule_id',
        ];

        $join = [
            '[><]' . ScheduleTaskUserModel::$table . ' (stu)' => ['st_id' => 'st.id'],
        ];

        return MysqlDB::getDB()->select(self::$table . ' (st)', $join, $columns, $where);
    }
}