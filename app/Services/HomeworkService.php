<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/16
 * Time: 16:11
 */
namespace App\Services;


use App\Models\HomeworkModel;


class HomeworkService
{
    /** 创建作业
     * @param $opern_id
     * @param $book_id
     * @param $teacher_id
     * @param $student_id
     * @param $days_limit
     * @param $content
     * @return int|mixed|null|string
     */
    public static function createHomework($opern_id, $book_id, $teacher_id, $student_id, $days_limit, $content)
    {
        $create_time = time();
        return HomeworkModel::createHomework($opern_id, $book_id, $teacher_id, $student_id, $create_time,
            $create_time + $days_limit * 3600 * 24, $content);
    }

    /**
     * 检查是否完成作业
     * @param array $playInfo
     * @param array $unFinishedHomeWorks
     * @return array
     */
    private static function _checkHomework($playInfo, $unFinishedHomeWorks){
        $scoreDetail = $playInfo['score_detail'];
        $finished = [];
        foreach ($unFinishedHomeWorks as $homework){
            foreach ($homework as $assessment => $score){
                if(array_key_exists($assessment, $scoreDetail)){
                    if($homework[$assessment] >= $scoreDetail[$assessment]){
                        array_push($finished, $homework);
                    }
                }
            }
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
        $opernId = $playInfo['opern_id'];
        $unFinishedHomeWorks = HomeworkModel::getStudentUnfinishedWork($studentId, $opernId);
        $finishedHomework = HomeworkService::_checkHomework($playInfo, $unFinishedHomeWorks);
        return $finishedHomework;
    }

}
