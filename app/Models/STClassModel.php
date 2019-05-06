<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-26
 * Time: 21:16
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;

/**
 * 班课
 * Class STClassModel
 * @package App\Models
 */
class STClassModel extends Model
{
    public static $table = "class";
    const STATUS_CANCEL_AFTER_BEGIN = -1;//开课后取消
    const STATUS_CANCEL = 0;//取消排课
    const STATUS_NORMAL = 1;//正常排课
    const STATUS_BEGIN = 2;//开课
    const STATUS_END = 3;//结课
    const STATUS_CHANGE = 4; // 临时调课

    public static function addSTClass($stc)
    {
        return self::insertRecord($stc);
    }

    public static function updateSTClass($id, $stc)
    {
        return self::updateRecord($id, $stc);
    }

    public static function getList($params, $page = -1, $count = 20)
    {
        $db = MysqlDB::getDB();
        $where = "";
        global $orgId;
        if ($orgId > 0) {
            $where = " where c.org_id = " . $orgId;
        }
        if (!empty($params['campus_id'])) {
            $where .= " and c.campus_id = " . $params['campus_id'];
        }
        if (!empty($params['name'])) {
            $where .= " and c.name like '%" . $params['name'] . "%'";
        }
        if (!empty($params['start_date'])) {
            $where .= " and ct.expire_start_date >=" . $params['start_date'];
        }
        if (is_numeric($params['status'])) {
            $where .= " and c.status = " . $params['status'];
        }
        if (!empty($params['student_id'])) {
            $where .= " and scu.user_id =" . $params['student_id'] ;
        }
        if (!empty($params['teacher_id'])) {
            $where .= " and tcu.user_id =" . $params['teacher_id'];
        }
        if (!empty($params['h_teacher_id'])) {
            $where .= " and tcu1.user_id =" . $params['h_teacher_id'];
        }
        if (!empty($params['classroom_id'])) {
            $where .= " and ct.classroom_id = " . $params['classroom_id'];
        }
        if (!empty($params['course_id'])) {
            $where .= " and ct.course_id = " . $params['course_id'];
        }
        $where .= " and c.status != " . self::STATUS_CHANGE . " and c.status != " . self::STATUS_CANCEL_AFTER_BEGIN;
        $select = " select distinct c.*,t.name as teacher_name, t1.name as h_teacher_name, cm.name as campus_name";
        $sql = "
            from " . self::$table . " as c 
            inner join " . CampusModel::$table . " as cm on c.campus_id = cm.id
            inner join " . ClassTaskModel::$table . " as ct on c.id = ct.class_id
            left join " . ClassUserModel::$table . " as scu on scu.class_id = c.id and scu.user_role = " . ClassUserModel::USER_ROLE_S . " and scu.status= " . ClassUserModel::STATUS_NORMAL ."
            left join " . ClassUserModel::$table . " as tcu on tcu.class_id = c.id and tcu.user_role = " . ClassUserModel::USER_ROLE_T . " and tcu.status= " . ClassUserModel::STATUS_NORMAL . "
            left join " . ClassUserModel::$table . " as tcu1 on tcu1.class_id = c.id and tcu1.user_role = " . ClassUserModel::USER_ROLE_HT . " and tcu1.status= " . ClassUserModel::STATUS_NORMAL . "
            left join " . TeacherModel::$table . " as t on tcu.user_id = t.id
            left join " . TeacherModel::$table . " as t1 on tcu1.user_id = t1.id
            ";
        $sql .= $where;
        $num = 0;
        if ($page != -1) {
            $num  = $db->query("select count(distinct c.id) as num " . $sql)->fetch(\PDO::FETCH_COLUMN);
            $page = empty($page) ? $page = 1 : $page;
            $sql .= " order by id desc limit " . ($page - 1) * $count . "," . $count;
        }
        $res = $db->query($select . $sql)->fetchAll(\PDO::FETCH_ASSOC);
        return [$num, $res];
    }

    /**
     * @param $id
     * @return mixed|void
     */
    public static function getDetail($id)
    {
        return MysqlDB::getDB()->get(self::$table . " (stc)", [
            '[><]' . CampusModel::$table . " (cm)" => ['stc.campus_id' => 'id']
        ], [
            'stc.id',
            'stc.name',
            'stc.create_time',
            'stc.update_time',
            'stc.class_lowest',
            'stc.class_highest',
            'stc.org_id',
            'stc.campus_id',
            'stc.status',
            'stc.lesson_num',
            'stc.finish_num',
            'stc.student_num',
            'cm.name (campus_name)',
        ], ['stc.id' => $id]);

    }

}