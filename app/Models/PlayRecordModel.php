<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/2/22
 * Time: 2:36 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\Util;

class PlayRecordModel extends Model
{
    public static $table = 'play_record';

    /** 练琴类型 */
    const TYPE_DYNAMIC = 0;         // 动态曲谱
    const TYPE_AI = 1;              // ai曲谱

    /** 课上课下 */
    const TYPE_ON_CLASS = 0;       // 课上练琴
    const TYPE_OFF_CLASS = 1;      // 课下练琴


    public static function getPlayRecordByLessonId($lessonId, $studentId,
                                                   $recordType=null,
                                                   $createdTime=null,
                                                   $endTime=null){
        $db = MysqlDB::getDB();
        $where = ['lesson_id' => $lessonId,
                  'student_id' => $studentId,
                  'ORDER' => ['created_time' => 'DESC']
        ];
        if (!empty($recordType)){
            $where['lesson_type'] = $recordType;
        }
        if (!empty($createdTime) && !empty($endTime)){
            $where['created_time[>=]'] = $createdTime;
            $where['created_time[<=]'] = $endTime;

        }
        $result = $db->select(PlayRecordModel::$table, '*', $where);
        return $result;
    }

    /** 获取学生课程报告
     * @param $student_id
     * @param $start_time
     * @param $end_time
     * @return array|null
     */
    public static function getPlayRecordReport($student_id, $start_time, $end_time){
        $sql = "select 
                    lesson_id, 
                    lesson_type, 
                    count(lesson_sub_id) as sub_count,
                    sum(duration) as duration ,
                    sum(if(lesson_type=0 and lesson_sub_id is null, 1, 0)) as dmc,
                    sum(if(lesson_type=1 and lesson_sub_id is null, 1, 0)) as ai, 
                    max(if(lesson_type=0 and lesson_sub_id is null, score, 0)) as max_dmc, 
                    max(if(lesson_type=1 and lesson_sub_id is null, score, 0)) as max_ai
            from play_record 
            where 
                student_id = :student_id and 
                created_time >= :start_time and 
                created_time <= :end_time
            group by 
              lesson_id, lesson_type";
        $map = [":student_id" => $student_id, ":start_time" => $start_time, ":end_time" => $end_time];
        $db = MysqlDB::getDB();
        $result = $db->queryAll($sql, $map);
        return $result;
    }

    /**
     * @param $lesson_id
     * @param $student_id
     * @param $start_time
     * @param $end_time
     * @return mixed
     */
    public static function getWonderfulAIRecordId($lesson_id, $student_id, $start_time, $end_time) {
        $db = MysqlDB::getDB();
        $result = $db->get(self::$table, ["ai_record_id", "score"], [
            "lesson_id" => $lesson_id,
            "student_id" => $student_id,
            "created_time[>=]" => $start_time,
            "created_time[<]" => $end_time,
            "lesson_type" => self::TYPE_AI,
            "ORDER" => [
                "score" => "DESC"
            ]
        ]);
        return $result;
    }

    /**
     * 查询指定机构日报
     * 指定学生id时查询指定学生日报
     * 否则查询所有学生日报
     * @param $orgId
     * @param $studentId
     * @param $startTime
     * @param $endTime
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function selectReport($orgId, $studentId, $startTime, $endTime, $page, $count, $params)
    {
        $p   = PlayRecordModel::$table;
        $s   = StudentModel::$table;
        $so  = StudentOrgModel::$table;
        $sch = ScheduleModel::$table;
        $su  = ScheduleUserModel::$table;
        $t   = TeacherModel::$table;
        $e   = EmployeeModel::$table;

        $ltd = PlayRecordModel::TYPE_DYNAMIC;
        $lta = PlayRecordModel::TYPE_AI;
        $urt = ScheduleUserModel::USER_ROLE_TEACHER;

        $limit = Util::limitation($page, $count);

        $map = [
            ':start_time' => $startTime,
            ':end_time'   => $endTime,
            ':org_id'     => $orgId
        ];

        $where = '';

        //lesson_type=0也是一种状态，所以这里使用isset
        if(isset($params['lesson_type'])) {
            $where .= ' and p.lesson_type = :lesson_type ';
            $map[':lesson_type'] = $params['lesson_type'];
        }
        if(isset($params['role_id'])) {
            $where .= ' and s.cc_id = :role_id ';
            $map[':role_id'] = $params['role_id'];
        }

        if(!empty($studentId)) {
            $sql = "select p.lesson_id,
                   p.lesson_type,
                   p.student_id,
                   p.schedule_id,
                   count(p.lesson_sub_id)                                             as sub_count,
                   sum(p.duration)                                                    as duration,
                   sum(if(p.lesson_type={$ltd} and p.lesson_sub_id is null, 1, 0))       as dmc,
                   sum(if(p.lesson_type={$lta} and p.lesson_sub_id is null, 1, 0))       as ai,
                   max(if(p.lesson_type={$ltd} and p.lesson_sub_id is null, p.score, 0)) as max_dmc,
                   max(if(p.lesson_type={$lta} and p.lesson_sub_id is null, p.score, 0)) as max_ai,
                   s.name                                                             as student_name,
                   t.name                                                             as teacher_name,
                   e.name                                                             as cc_name
            from {$p} p
                        inner join {$s} s on p.student_id = s.id
                        inner join {$so} so on so.student_id = s.id and so.org_id = :org_id
                        left join {$sch} sch on sch.id = p.schedule_id
                        left join {$su} su on su.schedule_id = sch.id and su.user_role = {$urt}
                        left join {$t} t on t.id = su.user_id
                        left join {$e} e on s.cc_id = e.id
            where p.student_id = :student_id
              and p.created_time >= :start_time
              and p.created_time <= :end_time
              {$where}
            group by p.lesson_id, p.lesson_type {$limit}";

            $totalSql = "select count(*) count from (select p.id
            from {$p} p
                        inner join {$s} s on p.student_id = s.id
                        inner join {$so} so on so.student_id = s.id and so.org_id = :org_id
            where p.student_id = :student_id
              and p.created_time >= :start_time
              and p.created_time <= :end_time
              {$where}
            group by p.lesson_id, p.lesson_type) s2";

            $map[':student_id'] = $studentId;
        } else {
            $sql = "select p.lesson_id,
                   p.lesson_type,
                   p.student_id,
                   p.schedule_id,
                   so.org_id,
                   count(p.lesson_sub_id)                                             as sub_count,
                   sum(p.duration)                                                    as duration,
                   sum(if(p.lesson_type={$ltd} and p.lesson_sub_id is null, 1, 0))       as dmc,
                   sum(if(p.lesson_type={$lta} and p.lesson_sub_id is null, 1, 0))       as ai,
                   max(if(p.lesson_type={$ltd} and p.lesson_sub_id is null, p.score, 0)) as max_dmc,
                   max(if(p.lesson_type={$lta} and p.lesson_sub_id is null, p.score, 0)) as max_ai,
                   s.name                                                             as student_name,
                   t.name                                                             as teacher_name,
                   e.name                                                             as cc_name
            from {$p} p
                   inner join {$s} s on p.student_id = s.id
                   inner join {$so} so on so.student_id = s.id and so.org_id = :org_id
                   left join {$sch} sch on sch.id = p.schedule_id
                   left join {$su} su on su.schedule_id = sch.id and su.user_role = {$urt}
                   left join {$t} t on t.id = su.user_id
                   left join {$e} e on s.cc_id = e.id
                   where p.created_time >= :start_time
                     and p.created_time <= :end_time
                     {$where}
            group by p.student_id {$limit}";

            $totalSql = "select count(*) count from (select p.id
            from {$p} p
                        inner join {$s} s on p.student_id = s.id
                        inner join {$so} so on so.student_id = s.id and so.org_id = :org_id
            where p.created_time >= :start_time
              and p.created_time <= :end_time
              {$where}
            group by p.student_id) s2";
        }

        $db = MysqlDB::getDB();

        $records = $db->queryAll($sql, $map);

        $total = $db->queryAll($totalSql, $map);

        return [$records, $total[0]['count']];
    }

    public static function getPlayRecordListByHomework($homeworkId, $taskId, $lessonId,
                                                       $startTime, $endTime, $statistic=false, $page=null, $limit=null){
        $db = MysqlDB::getDB();

        if ($statistic){
            $fields = "count(1) as play_count, max(pr.score) as max_score, " .
                "sum(pr.duration) as duration ";
        } else{
            $fields = "pr.duration, pr.score, pr.created_time ";
        }

        $map = [
            "start_time" => $startTime,
            "end_time" => $endTime,
            "lesson_id" => $lessonId,
            "task_id" => $taskId,
            "homework_id" => $homeworkId
        ];
        $sql = "select " . $fields . " from " . self::$table . " as pr left join " . HomeworkCompleteModel::$table .
            " as hc on hc.play_record_id = pr.id left join " . HomeworkTaskModel::$table .
            " as ht on ht.id=hc.task_id " . "left join " . HomeworkModel::$table . " as h on h.id = ht.homework_id 
            where pr.created_time>=:start_time and pr.created_time <= :end_time 
            and pr.lesson_id=:lesson_id and hc.task_id=:task_id and hc.homework_id=:homework_id ";

        if (!$statistic){
            $sql = $sql . " order by h.created_time desc ";
            if (!empty($page) and !empty($limit)){
                $sql = $sql . " limit :limit offset :offset";
                $map["limit"] = $limit;
                $map["offset"] = ($page - 1) * $limit;
            }
        }
        $result = $db->queryAll($sql, $map);
        return $result;
    }
}
