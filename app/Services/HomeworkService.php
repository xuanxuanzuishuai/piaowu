<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/16
 * Time: 16:11
 */
namespace App\Services;


use App\Libs\Util;
use App\Models\HomeworkCompleteModel;
use App\Models\HomeworkModel;
use App\Models\HomeworkTaskModel;
use App\Libs\SimpleLogger;
use App\Models\PlayRecordModel;
use App\Libs\OpernCenter;
use App\Models\ScheduleUserModelForApp;


class HomeworkService
{
    /** 创建作业
     * @param $schedule_id
     * @param $org_id
     * @param $teacher_id
     * @param $student_id
     * @param $days_limit
     * @param $remark
     * @param $content
     * @return int|mixed|null|string
     */
    public static function createHomework($schedule_id, $org_id, $teacher_id, $student_id, $days_limit, $remark, $content)
    {
        $created_time = time();
        $end_time = strtotime('today') + $days_limit * 86400 + 86399;
        $homework_id = HomeworkModel::createHomework($schedule_id, $org_id, $teacher_id, $student_id, $created_time,
            $end_time, $remark);
        foreach ($content as $task) {
            HomeworkTaskModel::createHomeworkTask($homework_id, $task["lesson_id"], $task["lesson_name"], $task["collection_id"],
                $task["collection_name"], json_encode($task["baseline"]));
        }
        return $homework_id;
    }

    /**
     * 获取学生当前未过期的作业
     * @param int $studentId 学生ID
     * @param int $lessonId 曲谱ID
     * @return array
     */
    public static function getStudentUnexpiredWork($studentId, $lessonId=null){
        $where = [
            HomeworkModel::$table . ".student_id" => $studentId,
            HomeworkModel::$table . ".end_time[>]" => time(),
            //HomeworkTaskModel::$table . ".is_complete" => HomeworkTaskModel::TYPE_UNCOMPLETE
        ];
        if (!empty($lessonId)){
            $where[HomeworkTaskModel::$table . ".lesson_id"] = $lessonId;
        }
        return HomeworkModel::getHomeworkList($where);
    }

    /** 获取学生作业列表
     * @param $studentId
     * @param null $teacherId
     * @param int $pageId
     * @param int $pageLimit
     * @param int $startTime
     * @param $endTime
     * @param $homework_ids
     * @return array
     */
    public static function getStudentHomeWorkList($studentId, $teacherId=null, $pageId=-1,
                                                  $pageLimit=0, $startTime=null, $endTime=null, $homework_ids=null)
    {
        $where = [
            HomeworkModel::$table . ".student_id" => $studentId,
            'ORDER' => ['created_time' => 'DESC']
        ];
        if (!empty($teacherId)) {
            $where[HomeworkModel::$table . ".teacher_id"] = $teacherId;
        }
        if ($pageId > 0 && $pageLimit > 0) {
            $where['LIMIT'] = [($pageId - 1) * $pageLimit, $pageLimit];
        }
        if (!empty($startTime)){
            $where[HomeworkModel::$table . ".created_time" >= $startTime];
        }
        if (!empty($endTime)){
            $where[HomeworkModel::$table . ".created_time" <= $endTime];
        }

        if ($homework_ids != null){
            $where[HomeworkTaskModel::$table . ".homework_id"] = $homework_ids;
        }

        return HomeworkModel::getHomeworkList($where);
    }

    public static function getScheduleHomeWorkList($schedule_id){
        $where = [
            HomeworkModel::$table . ".schedule_id" => $schedule_id,
        ];
        return HomeworkModel::getHomeworkList($where);
    }

    /**
     * 私有方法，检查作业
     * @param array $playInfo
     * @param array $homeworks
     * @return array
     */
    private static function _checkHomework($playInfo, $homeworks){
        if(empty($homeworks)){
            return [];
        }
        $scoreDetail = $playInfo['score_detail'];
        $finished = [];
        foreach ($homeworks as $homework){
            $baseline = json_decode($homework['baseline'], true);
            $meetRequirement = true;
            foreach ($baseline as $requirement => $score){
                if(array_key_exists($requirement, $scoreDetail)&&($scoreDetail[$requirement] >= $score)){
                    continue;
                }else{
                    $meetRequirement = false;
                    break;
                }
            }
            if($meetRequirement){
                array_push($finished, $homework);
            }
        }

        if(!empty($finished)){
            // 插入homework_complete表
            $recordId = $playInfo['record_id'];
            HomeworkCompleteModel::finishHomework($recordId, $finished);
            // 标记task完成
            HomeworkTaskModel::completeTask($finished);
        }
        return $finished;
    }

    /**
     * 根据练琴结果检查作业
     * @param int $studentId 学生ID
     * @param mixed $playInfo 练琴结果信息
     * @return array 完成的作业
     */
    public static function checkHomework($studentId, $playInfo){
        $lessonId = $playInfo['lesson_id'];
        $allHomeworks = self::getStudentUnexpiredWork($studentId, $lessonId);
        // 理论上一定有作业
        if(empty($allHomeworks)){
            return [null, [], []];
        }
        $finishedHomework = self::_checkHomework($playInfo, $allHomeworks);
        return [null, $allHomeworks, $finishedHomework];
    }

    /**
     * 某条作业的练习纪录
     * @param int $studentId 学生ID
     * @param int $taskId
     * @param int $teacherId
     * @param int $startTime
     * @param int $endTime
     * @return mixed
     */
    public static function getStudentHomeworkPractice($studentId, $taskId, $teacherId=null, $startTime=null,
                                                      $endTime=null, $flunked=false){

        // 获取作业
        $where = [
            HomeworkTaskModel::$table . ".id" => $taskId
        ];

        if (!empty($studentId)){
            $where[HomeworkModel::$table . ".student_id"] = $studentId;
        }
        if (!empty($teacherId)){
            $where[HomeworkModel::$table . ".teacher_id"] = $teacherId;
        }
        $homework = HomeworkModel::getHomeworkList($where);
        if(empty($homework)){
            return [null, null];
        }
        $homework = $homework[0];

        if ($startTime < $homework['created_time'] or empty($startTime)){
            $startTime = $homework['created_time'];
        }
        if ($endTime > $homework['end_time'] or empty($endTime)){
            $endTime = $homework['end_time'];
        }

        $plays = PlayRecordModel::getPlayRecordList($homework['id'], $taskId, $homework["lesson_id"],
            $startTime, $endTime, false, null,null, $homework["student_id"], $flunked);

        return [$homework, $plays];
    }

    /**
     * 获取某条作业的某天的练习记录
     * @param $studentId
     * @param $taskId
     * @param null $teacherId
     * @param null $date
     * @return mixed
     */
    public static function getStudentDayHomeworkPractice($studentId, $taskId, $teacherId=null, $date=null){
        $startTime = strtotime($date);
        $endTime = $startTime + 86399;
        return self::getStudentHomeworkPractice($studentId, $taskId, $teacherId, $startTime, $endTime);
    }

    /**
     * 获取某个课程的练习记录
     * @param $studentId
     * @param $lessonId
     * @param $startTime
     * @param $endTime
     * @return array|null
     */
    public static function getStudentLessonPractice($studentId, $lessonId, $startTime, $endTime){
        $plays = PlayRecordModel::getPlayRecordList(null, null, $lessonId,
            $startTime, $endTime, false, $studentId);
        return $plays;
    }

    public static function getStudentDayLessonPractice($studentId, $lessonId, $date){
        $startTime = strtotime($date);
        $endTime = $startTime + 86399;
        $plays = self::getStudentLessonPractice($studentId, $lessonId,
            $startTime, $endTime);
        return $plays;
    }

    /**
     * 根据课程ID获取最优弹奏记录
     * @param $lessonIds
     * @return array
     */
    public static function getBestPlayRecordByLessonId($lessonIds, $studentId){
        $playRecords = PlayRecordModel::getPlayRecordByLessonId($lessonIds, $studentId, PlayRecordModel::TYPE_AI);
        $container = array();
        foreach($playRecords as $record){
            $lesson_id = $record['lesson_id'];
            $score_info = json_decode($record['data'], true);
            $new_score = $score_info['score'];
            if(array_key_exists($lesson_id, $container)){
                $current_score = $container[$lesson_id];
                if($current_score < $new_score){
                    $container[$lesson_id] = $score_info;
                }
            }else{
                $container[$lesson_id] = $score_info;
            }
        }
        return $container;
    }

    /**
     * 爱学琴老师端回课数据
     * @param int $teacherId
     * @param int $studentId
     * @return array
     */
    public static function scheduleFollowUp($teacherId, $studentId, $proVer=1){
        // 获取师徒二人上节课
        $scheduleWhere = [
            "ORDER" => ['create_time' => 'DESC'],
            "LIMIT" => 1
        ];
        $schedule = ScheduleUserModelForApp::getUserSchedule($studentId, $teacherId, $scheduleWhere);
        if(empty($schedule)){
            return [[], [], [], []];
        }

        // 获取师徒二人最近的一次作业
        $where = [
            HomeworkModel::$table . ".student_id" => $studentId,
            HomeworkModel::$table . ".teacher_id" => $teacherId,
            HomeworkModel::$table . ".schedule_id" => $schedule[0]['id'],
            "ORDER" => ['created_time' => 'DESC'],
            "LIMIT" => 1
        ];
        $homeworkId = HomeworkModel::getRecord($where, 'id', false);
        if(empty($homeworkId)){
            return [[], [], [], []];
        }
        $homeworkWhere = [HomeworkModel::$table . ".id" => $homeworkId];
        $homework = HomeworkModel::getHomeworkList($homeworkWhere);

        // 作业的练习统计
        $lessonIds = array_column($homework, 'lesson_id');
        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_TEACHER, $proVer);
        $bookInfo = $opn->lessonsByIds($lessonIds);
        $books = [];
        $collectionIds = [];
        foreach ($bookInfo['data'] as $book){
            $books[$book['lesson_id']] = [
                'collection_name' => $book['collection_name'],
                'res' => $book['resources'] && $book['resources'][0]['url'] ? $book['resources'][0]['url']:'',
                'collection_cover' => $book['collection_cover'],
                'collection_id' => $book['collection_id'],
                'score_id' => $book['opern_id'],
                'is_free' => $book['freeflag'] ? '1' : '0',
                'lesson_name' => $book['lesson_name']
            ];
            array_push($collectionIds, $book['collection_id']);
        }

        list($start, $end) = [$homework[0]['created_time'], $homework[0]['end_time']];
        $allPlays = PlayRecordModel::getPlayRecordByLessonId(
            $lessonIds, $studentId, null, $start, $end
        );

        // 统计
        $statistics = [];
        foreach ($allPlays as $play){
            $lessonId = $play['lesson_id'];
            if (!isset($statistics[$lessonId])){
                $statistics[$lessonId] = [
                    'practice_time' => 0,
                    'step_times' => 0,
                    'whole_times' => 0,
                    'whole_best' => 0,
                    'ai_times' => 0,
                    'ai_best' => 0
                ];
            }
            $statistics[$lessonId]['practice_time'] += $play['duration'];
            $score = Util::floatIsInt($play['score']) ? (int)$play['score'] : $play['score'];
            if ($play['lesson_type'] == PlayRecordModel::TYPE_AI){
                $statistics[$lessonId]['ai_times'] += 1;
                if ($score > $statistics[$lessonId]['ai_best']){
                    $statistics[$lessonId]['ai_best'] = $score;
                }
            } else {
                if(!empty($play['lesson_sub_id'])){
                    $statistics[$lessonId]['step_times'] += 1;
                }else{
                    $statistics[$lessonId]['whole_times'] += 1;
                    if ($score > $statistics[$lessonId]['whole_best']){
                        $statistics[$lessonId]['whole_best'] = $score;
                    }
                }
            }
        }

        return [$homework, $statistics, $books, $collectionIds];
    }


    public static function makeFollowUp($teacherId, $studentId, $proVer=1){
        // 获取回课数据
        list($tasks, $statistics, $books, $collectionIds) = HomeworkService::scheduleFollowUp(
            $teacherId, $studentId, $proVer
        );

        // 组装数据
        $homework = [];
        foreach ($tasks as $task) {
            $taskBase = [
                'id' => $task['lesson_id'],
                'name' => $books[$task['lesson_id']] ? $books[$task['lesson_id']]['lesson_name'] : '',
                'complete' => $task['complete']
            ];
            $play = $statistics[$task['lesson_id']];
            if (empty($play)) {
                $play = [
                    'practice_time' => 0,
                    'step_times' => 0,
                    'whole_times' => 0,
                    'whole_best' => 0,
                    'ai_times' => 0,
                    'ai_best' => 0
                ];
            }
            $book = $books[$task['lesson_id']];
            $homework[] = array_merge($taskBase, $play, $book);
        }

        // 最近教材
        if (!empty($collectionIds)) {
            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, 1);
            $result = $opn->collectionsByIds($collectionIds);
            if (empty($result) || !empty($result['errors'])) {
                $recentCollections = [];
            } else {
                $recentCollections = OpernService::appFormatCollections($result['data']);
            }
        } else {
            $recentCollections = [];
        }
        return [$homework, $recentCollections];
    }

}
