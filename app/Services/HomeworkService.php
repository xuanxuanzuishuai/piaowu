<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/16
 * Time: 16:11
 */
namespace App\Services;


use App\Models\HomeworkCompleteModel;
use App\Models\HomeworkModel;
use App\Models\HomeworkTaskModel;
use App\Libs\SimpleLogger;
use App\Models\PlayRecordModel;



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
        $homework_id = HomeworkModel::createHomework($schedule_id, $org_id, $teacher_id, $student_id, $created_time,
            $created_time + $days_limit * 3600 * 24, $remark);
        foreach ($content as $task) {
            HomeworkTaskModel::createHomeworkTask($homework_id, $task["lesson_id"], $task["lesson_name"], $task["collection_id"],
                $task["collection_name"], json_encode($task["baseline"]));
        }
        return $homework_id;
    }

    /**
     * 获取学生当前未完成的作业
     * @param int $studentId 学生ID
     * @param int $lessonId 曲谱ID
     * @return array
     */
    public static function getStudentUnfinishedWork($studentId, $lessonId=null){
        $where = [
            HomeworkModel::$table . ".student_id" => $studentId,
            HomeworkModel::$table . ".end_time[>]" => time(),
            HomeworkTaskModel::$table . ".is_complete" => HomeworkTaskModel::TYPE_UNCOMPLETE
        ];
        if (!empty($lessonId)){
            $where[HomeworkTaskModel::$table . ".lesson_id"] = $lessonId;
        }
        return HomeworkModel::getHomeworkList($where);
    }

    /**
     * 获取学生作业列表
     * @param int $studentId 学生ID
     * @param int $pageId 页码
     * @param int $pageLimit 每页条数
     * @return array
     */
    public static function getStudentHomeWorkList($studentId, $pageId, $pageLimit){
        $where = [
            HomeworkModel::$table . ".student_id" => $studentId,
            'ORDER' => ['created_time' => 'DESC']
        ];
        if ($pageId > 0 && $pageLimit > 0) {
            $where['LIMIT'] = [($pageId - 1) * $pageLimit, $pageLimit];
        }
        return HomeworkModel::getHomeworkList($where);
    }

    /**
     * 私有方法，检查作业
     * @param array $playInfo
     * @param array $unFinishedHomeWorks
     * @return array
     */
    private static function _checkHomework($playInfo, $unFinishedHomeWorks){
        if(empty($unFinishedHomeWorks)){
            return [];
        }
        $scoreDetail = $playInfo['score_detail'];
        $finished = [];
        foreach ($unFinishedHomeWorks as $homework){
            $baseLine = json_decode($homework['baseline']);
            $meetRequirment = true;
            foreach ($baseLine as $requirement => $score){
                if(array_key_exists($requirement, $scoreDetail)&&($scoreDetail[$requirement] >= $score)){
                    continue;
                }else{
                    $meetRequirment = false;
                    break;
                }
            }
            if($meetRequirment){
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
        $unFinishedHomeWorks = self::getStudentUnfinishedWork($studentId, $lessonId);
        $finishedHomework = self::_checkHomework($playInfo, $unFinishedHomeWorks);
        return [null, $unFinishedHomeWorks, $finishedHomework];
    }

    /**
     * 某条作业的练习纪录
     * @param int $studentId 学生ID
     * @param int $lessonId  课程ID(曲谱ID)
     * @return mixed
     */
    public static function getStudentHomeworkPractice($studentId, $lessonId){
        // 获取作业
        $where = [
            HomeworkModel::$table . ".student_id" => $studentId,
            HomeworkTaskModel::$table . ".lesson_id" => $lessonId
        ];
        $homework = HomeworkModel::getHomeworkList($where);

        // 获取达成作业子任务的练琴记录
        $taskId = array_column($homework, 'task_id');
        $playRecordMeetRequiremnet = HomeworkCompleteModel::getPlayRecordIdByTaskId($taskId);
        $playRecordMeetRequiremnetIds = array_column($playRecordMeetRequiremnet, 'play_record_id');
        // 全部练习纪录
        $playRecords = PlayRecordModel::getPlayRecordByLessonId($lessonId, PlayRecordModel::TYPE_AI);
        $plays = [];
        foreach ($playRecords as $play){
            if(in_array($play['id'] , $playRecordMeetRequiremnetIds)){
                $play['complete'] = 1;
            }else{
                $play['complete'] = 0;
            }
            array_push($plays,$play );
        }
        return [$homework, $plays];
    }

}
