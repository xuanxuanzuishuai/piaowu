<?php
/**
 * Created by PhpStorm.
 * User: dahua
 * Date: 2019/4/24
 * Time: 10:36
 */

namespace App\Models;
use App\Libs\MysqlDB;
use phpDocumentor\Reflection\Types\Integer;


class ScheduleExtendModel extends Model
{
    public static $table = "schedule_extend";

    /**
     * 写入一条报告
     * @param $data
     * @param bool $isOrg TODO $isOrg means what
     * @return int|mixed|null|string
     */
    public static function insertReport($data, $isOrg=false)
    {
        return self::insertRecord($data, $isOrg);
    }

    public static function getUserScheduleExtendDetail($schedule_id, $student_id=null) {

        $sql = "select 
                  se.schedule_id as schedule_id, 
                  se.opn_lessons as opn_lessons,
                  se.detail_score as detail_score,
                  se.class_score as class_score,
                  se.remark as remark,
                  tsu.user_id as teacher_id,
                  ssu.user_id as student_id,
                  s.start_time as start_time,
                  t.name as teacher_name,
                  stu.name as student_name
                from " . self::$table . " se " . " inner join " . ScheduleModel::$table .
            " s on se.schedule_id = s.id inner join " . ScheduleUserModel::$table .
            " tsu on tsu.schedule_id = s.id and tsu.user_role = " . ScheduleUserModel::USER_ROLE_TEACHER .
            " and tsu.user_status=" .ScheduleUserModel::TEACHER_STATUS_ATTEND . " inner join " . ScheduleUserModel::$table .
            " ssu on ssu.schedule_id=s.id and ssu.user_role = ". ScheduleUserModel::USER_ROLE_STUDENT .
            " and ssu.user_status=" . ScheduleUserModel::STUDENT_STATUS_ATTEND . " inner join " . TeacherModelForApp::$table .
            " t on t.id = tsu.user_id " . " inner join " . StudentModelForApp::$table . " stu on stu.id = ssu.user_id " .
            " where se.schedule_id = :schedule_id";

        $map = [":schedule_id" => $schedule_id];
        if (!empty($student_id)){
            $sql = $sql . " and ssu.user_id=:student_id ";
            $map[":student_id"] = $student_id;
        }

        $db = MysqlDB::getDB();
        $result = $db->queryAll($sql, $map);
        return $result;
    }

    public static function getList($params, $page=null, $limit=null){
        $db = MysqlDB::getDB();
        $where = [];
        $map = [];
        if (!empty($params["schedule_status"])){
            array_push($where, "s.status = :schedule_status");
            $map[":schedule_status"] = $params["schedule_status"];
        }

        if (!empty($params["student_id"])){
            array_push($where, "ssu.user_id = :student_id");
            $map[":student_id"] = $params["student_id"];
        }

        $limit_sql = "";
        if (!empty($page) and !empty($limit)){
            $limit_sql = " limit :offset, :limit ";
            $map[":offset"] = ($page - 1) * $limit;
            $map[":limit"] = (int)$limit;
        }
        $where_sql = join(" and ", $where);

        $sql = "select
                  se.detail_score, 
                  se.class_score, 
                  se.remark, 
                  s.start_time,
                  FROM_UNIXTIME(s.start_time, '%Y-%m-%d') as start_date
                from "
                  . self::$table . " as se 
                inner join " .
                  ScheduleModelForApp::$table . " as s 
                on 
                  s.id = se.schedule_id 
                inner join 
                  ". ScheduleUserModel::$table . " as ssu 
                on 
                  ssu.schedule_id = s.id and ssu.user_role=". ScheduleUserModel::USER_ROLE_STUDENT .
              " inner join " .
                  ScheduleUserModel::$table . " as tsu 
                on 
                  tsu.schedule_id=s.id and tsu.user_role=" . ScheduleUserModel::USER_ROLE_TEACHER . " where " .
                $where_sql . $limit_sql;

        return $db->queryAll($sql, $map);
    }
}
