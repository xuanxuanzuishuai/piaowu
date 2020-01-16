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
use Medoo\Medoo;

class PlayRecordModel extends Model
{
    public static $table = 'play_record';

    /** 练琴类型 */
    const TYPE_DYNAMIC = 0;         // 动态曲谱
    const TYPE_AI = 1;              // ai曲谱

    /** 练琴来源 */
    const CLIENT_STUDENT = 1;       // ai陪练
    const CLIENT_TEACHER = 2;       // 智能琴房
    const CLIENT_PANDA_MINI = 3;    // 熊猫小程序

    /** AI测评类型 */
    const AI_EVALUATE_PLAY = 1;      //演奏
    const AI_EVALUATE_AUDIO = 2;     //音频识别
    const AI_EVALUATE_FRAGMENT = 3;  //分段分手演奏

    /** 分手 */
    const CFG_HAND_BOTH = 1; // 双手
    const CFG_HAND_LEFT = 2; // 左手
    const CFG_HAND_RIGHT = 3; // 右手

    /** 模式 */
    const CFG_MODE_NORMAL = 1; // 正常(PK)
    const CFG_MODE_STEP = 2; // 跟弹(识谱)
    const CFG_MODE_SLOW = 3; // 慢练


    const RANK_LIMIT = 150;          //排行榜取前RANK_LIMIT名


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

    /**
     * 获取学生课程报告
     * @param $student_id
     * @param $start_time
     * @param $end_time
     * @param bool $order
     * @return array|null
     */
    public static function getPlayRecordReport($student_id, $start_time, $end_time, $order = false)
    {
        // 普通演奏数据（趣味练习、AI测评）
        $sql = "SELECT
    lesson_id,
    lesson_type,
    count(lesson_sub_id) AS sub_count,
    sum(duration) AS duration,
    sum(if(lesson_type=0 AND lesson_sub_id IS NULL, 1, 0)) AS dmc,
    sum(if(lesson_type=1 AND lesson_sub_id IS NULL AND ai_type != 3 , 1, 0)) AS ai,
    sum(if(lesson_type=1 AND lesson_sub_id IS NULL AND ai_type = 3 , 1, 0)) AS part,
    max(if(lesson_type=0 AND lesson_sub_id IS NULL, score, 0)) AS max_dmc,
    max(if(lesson_type=1 AND lesson_sub_id IS NULL AND ai_type != 3 , score, 0)) AS max_ai
FROM play_record
WHERE
    student_id = :student_id AND
    created_time >= :start_time AND
    created_time <= :end_time AND
    schedule_id IS NULL
GROUP BY
  lesson_id, lesson_type";

        if ($order) {
            $sql .= " order by created_time";
        }
        $map = [":student_id" => $student_id, ":start_time" => $start_time, ":end_time" => $end_time];
        $db = MysqlDB::getDB();
        $result = $db->queryAll($sql, $map);

        $formatRet = [];

        foreach ($result as $value) {
            array_push($formatRet, [
                "lesson_id" => $value["lesson_id"],
                "lesson_type" => $value["lesson_type"],
                "sub_count" => $value["sub_count"],
                "duration" => $value["duration"],
                "class_duration" => 0,
                "dmc" => Util::convertToIntIfCan($value["dmc"]),
                "ai" => Util::convertToIntIfCan($value["ai"]),
                "part" => Util::convertToIntIfCan($value["part"]),
                "max_dmc" => Util::convertToIntIfCan($value["max_dmc"]),
                "max_ai" => Util::convertToIntIfCan($value["max_ai"]),
            ]);
        }

        // 上课模式数据
        $classSql = "SELECT
    lesson_id, SUM(duration) AS sum_duration
FROM
    play_class_record
WHERE
    student_id = :student_id
        AND create_time >= :start_time
        AND create_time <= :end_time
GROUP BY lesson_id;";
        $classMap = [":student_id" => $student_id, ":start_time" => $start_time, ":end_time" => $end_time];
        $classResult = $db->queryAll($classSql, $classMap);

        foreach ($classResult as $value) {
            array_push($formatRet, [
                "lesson_id" => $value["lesson_id"],
                "lesson_type" => 0,
                "sub_count" =>0,
                "duration" => 0,
                "class_duration" => $value["sum_duration"],
                "dmc" => 0,
                "ai" => 0,
                "part" => 0,
                "max_dmc" => 0,
                "max_ai" => 0,
            ]);
        }

        return $formatRet;
    }

    /**
     *
     * @param $student_id
     * @param $start_time
     * @param $end_time
     * @return array|null
     */
    public static function getHomeworkByPlayRecord($student_id, $start_time, $end_time){
        $sql = "select hc.task_id as task_id, ht.lesson_id as lesson_id from " .
            self::$table . " as pr left join " . HomeworkCompleteModel::$table .
            " as hc on pr.id = hc.play_record_id left join " . HomeworkTaskModel::$table .
            " as ht on hc.task_id = ht.id where pr.schedule_id is null and pr.student_id=:student_id and pr.created_time>=:start_time 
            and pr.created_time <= :end_time and pr.lesson_type = " . self::TYPE_AI . " order by ht.id asc" ;
        $map = [
            ":student_id" => $student_id,
            ":start_time" => $start_time,
            ":end_time" => $end_time
        ];
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
            "ai_type[!]" => self::AI_EVALUATE_FRAGMENT,
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
        $p  = PlayRecordModel::$table;
        $s  = StudentModel::$table;
        $so = StudentOrgModel::$table;
        $e  = EmployeeModel::$table;
        $t  = TeacherModel::$table;
        $ts = TeacherStudentModel::$table;

        $ltd = PlayRecordModel::TYPE_DYNAMIC;
        $lta = PlayRecordModel::TYPE_AI;

        $limit = Util::limitation($page, $count);

        $where = '';
        $map = [];

        //lesson_type=0也是一种状态，所以这里使用isset
        if(isset($params['lesson_type'])) {
            $where .= ' and p.lesson_type = :lesson_type ';
            $map[':lesson_type'] = $params['lesson_type'];
        }
        if(!empty($params['cc_id'])) {
            $where .= ' and so.cc_id = :cc_id ';
            $map[':cc_id'] = $params['cc_id'];
        }
        if(!empty($params['student_id'])) {
            $where .= ' and p.student_id = :student_id ';
            $map[':student_id'] = $studentId;
        }
        if(!empty($params['teacher_name'])) {
            $where .= ' and t.name like :teacher_name ';
            $map[':teacher_name'] = "%{$params['teacher_name']}%";
        }

        $sql = "select p.lesson_id,
               p.created_time,
               e.name                                                                cc_name,
               p.lesson_type,
               p.student_id,
               p.schedule_id,
               count(p.lesson_sub_id)                                             as sub_count,
               sum(p.duration)                                                    as duration,
               sum(if(p.lesson_type = {$ltd} and p.lesson_sub_id is null, 1, 0))       as dmc,
               sum(if(p.lesson_type = {$lta} and p.lesson_sub_id is null, 1, 0))       as ai,
               max(if(p.lesson_type = {$ltd} and p.lesson_sub_id is null, p.score, 0)) as max_dmc,
               max(if(p.lesson_type = $lta and p.lesson_sub_id is null, p.score, 0)) as max_ai,
               s.name                                                             as student_name,
               t.name teacher_name
        from (select from_unixtime(created_time, '%Y-%m-%d') created_time,
                     lesson_id,
                     lesson_type,
                     student_id,
                     lesson_sub_id,
                     duration,
                     score,
                     schedule_id
              from {$p}
              where created_time between {$startTime} and {$endTime}) p
               inner join {$s} s on p.student_id = s.id
               inner join {$so} so on so.student_id = s.id and so.org_id = {$orgId}
               left join {$e} e on e.id = so.cc_id
               left join {$ts} ts on ts.student_id = p.student_id and ts.org_id = so.org_id
               left join {$t} t on t.id = ts.teacher_id
        where p.schedule_id is null
          {$where}
        group by p.created_time, p.lesson_type, p.student_id";

        $db = MysqlDB::getDB();

        $records = $db->queryAll("{$sql} {$limit}", $map);

        $total = $db->queryAll("select count(*) count from ({$sql}) b", $map);

        return [$records, $total[0]['count']];
    }

    /**
     * @param $homeworkId
     * @param $taskId
     * @param $lessonId
     * @param $startTime
     * @param $endTime
     * @param bool $statistic
     * @param null $page
     * @param null $limit
     * @param null $studentId
     * @param bool $flunked 是否获取不及格记录，默认不要
     * @param bool $no_schedule
     * @return array|null
     */
    public static function getPlayRecordList($homeworkId, $taskId, $lessonId,
                                             $startTime, $endTime, $statistic=false, $page=null,
                                             $limit=null, $studentId=null, $flunked=false, $no_schedule=false){
        $db = MysqlDB::getDB();
        // 根据是否有homeworkId和taskId来确定是否join homework_complete 表
        $join_hc = false;
        if (!empty($homeworkId) and !empty($taskId)){
            $join_hc = true;
        }

        if ($statistic){
            $fields = "count(1) as play_count, max(pr.score) as max_score, " .
                "sum(pr.duration) as duration ";
        } else{
            $fields = "
            pr.duration as duration, 
            pr.score as score, 
            pr.created_time as created_time, 
            pr.lesson_id as lesson_id, 
            pr.collection_id as collection_id, 
            pr.student_id as student_id,
            pr.schedule_id as schedule_id,
            pr.category_id as category_id,
            pr.lesson_type as lesson_type,
            pr.lesson_sub_id as lesson_sub_id,
            pr.data as data,
            pr.midi as midi,
            pr.ai_record_id as ai_record_id,
            pr.ai_type as ai_type,
            ";

            if ($join_hc){
                $fields = $fields . " hc.id as complete_id,
                                      hc.task_id as task_id,
                                      if(hc.id is null, 0, 1) as complete
                                    ";
            } else{
                $fields = $fields . "0 as complete ";
            }
        }

        $map = [
            ":start_time" => $startTime,
            ":end_time" => $endTime,
            ":lesson_id" => $lessonId,
        ];
        $selectTable = "select " . $fields . " from " . self::$table . " as pr ";
        $where = " where pr.created_time>=:start_time and pr.created_time <= :end_time and pr.lesson_type=" .self::TYPE_AI .
            " and pr.ai_type!=" . self::AI_EVALUATE_FRAGMENT .
            " and pr.lesson_id=:lesson_id and pr.client_type in (" . self::CLIENT_STUDENT . ", " . self::CLIENT_PANDA_MINI . ")";
        if (!empty($studentId)){
            $map[":student_id"] = $studentId;
            $where = $where . " and pr.student_id=:student_id ";
        }
        if ($no_schedule){
            $where = $where . " and pr.schedule_id is null ";
        }
        if ($join_hc){
            $selectTable = $selectTable . "left join " . HomeworkCompleteModel::$table .
                " as hc on pr.id = hc.play_record_id and hc.task_id=:task_id and hc.homework_id=:homework_id ";
            $map[":task_id"] = $taskId;
            $map[":homework_id"] = $homeworkId;

        }

        $sql = $selectTable . $where;

        if (!$statistic){
            $sql = $sql . " order by pr.created_time desc ";
            if (!empty($page) and !empty($limit)){
                $sql = $sql . " limit :limit offset :offset";
                $map[":limit"] = $limit;
                $map[":offset"] = ($page - 1) * $limit;
            }
        }
        $result = $db->queryAll($sql, $map);
        return $result;
    }

    /**
     * 某用户某月哪天练习过曲谱
     * @param $year
     * @param $month
     * @param $student_id
     * @return array|null
     */
    public static function getMonthPlayRecordStatistics($year, $month, $student_id){

        $start_time = strtotime($year . "-" . $month);
        $end_time = strtotime(date('Y-m-t', $start_time) . "23:59:59");
        $sql = "select distinct FROM_UNIXTIME(pr.created_time, '%Y-%m-%d') as play_date from " .
            self::$table . " as pr where pr.created_time >= :start_time and pr.created_time <= :end_time and pr.schedule_id is null
            and pr.student_id=:student_id order by pr.id "; // id排序应该就等同于created_time排序了，个人并不确定created_time是否含有index
        $map = [
            ":start_time" => $start_time,
            ":end_time" => $end_time,
            ":student_id" => $student_id
        ];
        $db = MysqlDB::getDB();
        $result = $db->queryAll($sql, $map);
        return $result;
    }

    public static function getRank($lessonId, $students=[]){
        $limit = self::RANK_LIMIT;
        $lessonType = PlayRecordModel::TYPE_AI;
        $cfgHandBoth = PlayRecordModel::CFG_HAND_BOTH;
        $cfgModeNormal = PlayRecordModel::CFG_MODE_NORMAL;
        $aiTypeFragement = PlayRecordModel::AI_EVALUATE_FRAGMENT;
        $lowestScore = 60;
        if(empty($students)){
            $sql = "SELECT * FROM
                      (SELECT play_record.id AS play_id,
                              play_record.score,
                              play_record.lesson_id,
                              play_record.student_id,
                              play_record.ai_record_id,
                              play_record.ai_type,
                              student.name
                      FROM play_record
                      LEFT JOIN student ON play_record.student_id = student.id
                      WHERE play_record.lesson_id = {$lessonId}
                        AND play_record.lesson_type = {$lessonType}
                        AND play_record.is_frag = 0
                        AND play_record.cfg_hand = {$cfgHandBoth}
                        AND play_record.cfg_mode = {$cfgModeNormal}
                        AND play_record.score >= {$lowestScore}
                        AND play_record.ai_type != {$aiTypeFragement}
                      ORDER BY score DESC) t
                  GROUP BY t.student_id
                  ORDER BY score DESC
                  LIMIT {$limit}";
        }else{
            $students = implode(',', $students);
            $students = '(' . $students . ')';
            $sql = "SELECT * FROM
                      (SELECT play_record.id AS play_id,
                              play_record.score,
                              play_record.lesson_id,
                              play_record.student_id,
                              play_record.ai_record_id,
                              play_record.ai_type,
                              student.name
                      FROM play_record
                      LEFT JOIN student ON play_record.student_id = student.id
                      WHERE play_record.lesson_id = {$lessonId}
                        AND play_record.lesson_type = {$lessonType}
                        AND play_record.is_frag = 0
                        AND play_record.cfg_hand = {$cfgHandBoth}
                        AND play_record.cfg_mode = {$cfgModeNormal}
                        AND play_record.score >= {$lowestScore}
                        AND play_record.student_id IN {$students}
                        AND play_record.ai_type!= {$aiTypeFragement}
                      ORDER BY score DESC) t
                  GROUP BY t.student_id
                  ORDER BY score DESC
                  LIMIT {$limit}";
        }
        $db = MysqlDB::getDB();
        $result = $db->queryAll($sql);
        return $result;
    }

    /**
     * 获取某天练琴的学生uuid
     * @param $date
     * @return array
     */
    public static function getDayPlayedStudents($date)
    {
        return MysqlDB::getDB()->select(self::$table, [
            '[><]' . StudentModel::$table => ['student_id' => 'id']
        ], StudentModel::$table . '.uuid', [
            self::$table . '.created_time[>]' => $date,
            self::$table . '.created_time[<]' => $date + 86400,
            'GROUP' => self::$table . '.student_id'
        ]);
    }

    /**
     * 演奏数据汇总
     * @param $studentId
     * @param $startTime
     * @param $endTime
     * @return mixed
     */
    public static function getStudentPlaySum($studentId, $startTime, $endTime)
    {
        $db = MysqlDB::getDB();
        $result = $db->get(self::$table, [
            'lesson_count' => Medoo::raw('COUNT(DISTINCT(lesson_id))'),
            'sum_duration' => Medoo::raw('SUM(duration)'),
        ], [
            'student_id' => $studentId,
            'created_time[<>]' => [$startTime, $endTime]
        ]);

        if (empty($result)) {
            return ['lesson_count' => 0, 'sum_duration' => 0];
        }

        $result['sum_duration'] = $result['sum_duration'] ?? 0;

        return $result;
    }

    /**
     * 个人练琴时长汇总
     * 包含练习模式和上课模式
     * @param $studentId
     * @return array
     */
    public static function studentTotalPlaySum($studentId)
    {
        $db = MysqlDB::getDB();

        $sql = "SELECT
    COUNT(DISTINCT lesson_id) AS lesson_count,
    SUM(duration) AS sum_duration
FROM
    ((SELECT
        student_id, lesson_id, duration
    FROM
        play_record
    WHERE student_id = :student_id) UNION ALL (SELECT
        student_id, lesson_id, duration
    FROM
        play_class_record
    WHERE student_id = :student_id)) records
;";
        $result = $db->queryAll($sql, [':student_id' => $studentId]);
        if (empty($result[0])) {
            return ['lesson_count' => 0, 'sum_duration' => 0];
        }

        return [
            'lesson_count' => $result[0]['lesson_count'],
            'sum_duration' => $result[0]['sum_duration'],
        ];
    }

    /**
     * 学生练琴汇总(按天)
     * @param $studentId
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public static function getStudentSumByDate($studentId, $startTime, $endTime)
    {
        $db = MysqlDB::getDB();

        $sql = "SELECT
    FROM_UNIXTIME(create_time, '%Y%m%d') AS play_date,
    COUNT(DISTINCT lesson_id) AS lesson_count,
    SUM(duration) AS sum_duration
FROM
    ((SELECT 
        lesson_id, duration, created_time AS create_time
    FROM
        play_record
    WHERE
        created_time >= :start_time
            AND created_time < :end_time
            AND student_id = :student_id) UNION ALL (SELECT 
        lesson_id, duration, create_time
    FROM
        play_class_record
    WHERE
        create_time >= :start_time
            AND create_time < :end_time
            AND student_id = :student_id)) records
GROUP BY FROM_UNIXTIME(create_time, '%Y%m%d');";

        $map = [
            ':start_time' => $startTime,
            ':end_time' => $endTime,
            ':student_id' => $studentId,
        ];

        $result = $db->queryAll($sql, $map);
        return $result;
    }
}
