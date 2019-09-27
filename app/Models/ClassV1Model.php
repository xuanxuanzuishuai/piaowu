<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2019/9/26
 * Time: 11:31 AM
 */

namespace App\Models;


use App\Libs\MysqlDB;

class ClassV1Model extends Model
{
    public static $table = 'class_v1';

    /** @var int 班级状态 0 废除 1 创建 2 开课 3 结课  */
    const STATUS_ABANDON = 0;
    const STATUS_CREATE = 1;
    const STATUS_BEGIN = 2;
    const STATUS_FINISH = 3;

    public static function addClass($name, $campusId, $desc, $employeeId)
    {
        $time = time();
        return self::insertRecord([
            'name' => $name,
            'campus_id' => $campusId,
            'desc' => $desc,
            'creator_id' => $employeeId,
            'create_time' => $time,
            'status' => self::STATUS_CREATE,
            'update_time' => $time,
            'operator_id' => $employeeId
        ]);
    }

    public static function modifyClass($classId, $name, $campusId, $desc, $employeeId)
    {
        $time = time();
        self::updateRecord($classId, [
            'name' => $name,
            'campus_id' => $campusId,
            'desc' => $desc,
            'update_time' => $time,
            'operator_id' => $employeeId
        ]);
    }

    public static function getClassList($page, $count, $params)
    {
        $where = " WHERE 1=1 ";
        $map = [];
        if (!empty($params['name'])) {
            $where .= " AND c.name like :name ";
            $map[':name'] = "%{$params['name']}%";
        }
        if (is_numeric($params['status'])) {
            $where .= " AND c.status = :status ";
            $map[':status'] = $params['status'];
        }

        if (is_numeric($params['teacher_id'])) {
            $where .= " AND t.id = :teacher_id ";
            $map[':teacher_id'] = $params['teacher_id'];
        }

        if (is_numeric($params['student_id'])) {
            $where .= " AND su.user_id = :student_id ";
            $map[':student_id'] = $params['student_id'];
        }

        $db = MysqlDB::getDB();

        $select = "SELECT c.id, c.name class_name, c.desc, c.status, c.create_time,
    campus.name campus_name, COUNT(DISTINCT su.user_id) s_count, COUNT(DISTINCT tu.user_id) t_count ";

        $query = " FROM " . ClassV1Model::$table . " c
        LEFT JOIN " . CampusModel::$table . " ON campus.id = c.campus_id
        LEFT JOIN " . ClassV1UserModel::$table . " su ON su.class_id = c.id
            AND su.user_role = " . ClassV1UserModel::ROLE_STUDENT . "
            AND su.status = " . ClassV1UserModel::STATUS_NORMAL . "
        LEFT JOIN " . ClassV1UserModel::$table . " tu ON tu.class_id = c.id
             AND tu.user_role in (" . ClassV1UserModel::ROLE_TEACHER . ", " . ClassV1UserModel::ROLE_T_MANAGER . ")
             AND tu.status = " . ClassV1UserModel::STATUS_NORMAL . "
        LEFT JOIN " . TeacherModel::$table . " t ON t.id = tu.user_id ";

        $query .= $where;
        $num  = $db->query("select count(distinct c.id) as num " . $query, $map)->fetch(\PDO::FETCH_COLUMN);

        if ($num == 0) {
            return [0, []];
        }
        $query .= "group by c.id order by c.id desc limit " . ($page - 1) * $count . "," . $count;

        $list = $db->queryAll($select . $query, $map);

        return [$num, $list];
    }
}