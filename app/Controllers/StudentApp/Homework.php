<?php
/**
 * Created by PhpStorm.
 * User: dahua
 * Date: 2019/4/17
 * Time: 16:40
 * 学生作业相关接口
 */

namespace App\Controllers\StudentApp;

use App\Libs\SimpleLogger;
use App\Controllers\ControllerBase;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\HomeworkService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

/**
 * 学生作业相关
 */
class Homework extends ControllerBase
{
    /**
     * 作业的练习记录
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    public function practiceRecord(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'lesson_id',
                'type' => 'required',
                'lesson_id_is_required' => 'lesson_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $userId = $this->ci['student']['id'];
        list($homework, $playRecord) = HomeworkService::getStudentHomeworkPractice($userId, $params['lesson_id']);
        if(empty($homework)){
            $errors = Valid::addAppErrors([], "homework_not_found");
            return $response->withJson($errors, StatusCode::HTTP_OK);
        }

        // 组装数据
        $returnData = [
            'homework' => [
                'id' => $homework[0]['id'],
                'task_id' => $homework[0]['task_id'],
                'baseline' => json_decode($homework[0]['baseline'])
            ],
            'play_record' => [],
        ];
        foreach ($playRecord as $item) {
            $temp = [
                'time' => $item['created_time'],
                'score' => $item['score'],
                'complete' => $item['complete'],
                'record_id' => $item['id']
            ];
            array_push($returnData['play_record'], $temp);
        }

        return $response->withJson(['code'=>0, 'data'=>$returnData], StatusCode::HTTP_OK);
    }

    /**
     * 作业列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response){
        $params = $request->getParams();
        list($pageId, $pageLimit) = Util::formatPageCount($params);
        $userId = $this->ci['student']['id'];
        $data = HomeworkService::getStudentHomeWorkList($userId, $pageId, $pageLimit);

        // 获取最好弹奏小分
        $lessonIds = array_column($data, 'lesson_id');
        $container = HomeworkService::getBestPlayRecordByLessonId($lessonIds);

        // 组装数据
        $temp = [];
        foreach ($data as $homework){
            // 获取最优小分
            $lesson_id = $homework['lesson_id'];
            $baseline = json_decode($homework['baseline'], 1);
            $score_info = $container[$lesson_id];
            if(!empty($score_info)){
                $pitch = $score_info['score_detail']['pitch'];
                $rhythm = $score_info['score_detail']['rhythm'];
            }else{
                $pitch = 0;
                $rhythm = 0;
            }

            // 以homework为单位聚合task
            $task = [
                'task_id' => $homework['task_id'],
                'lesson_id' => $homework['lesson_id'],
                'complete' => $homework['complete'],
                'score_detail' => [
                    'pitch' => ['high' => $pitch, 'baseline' => $baseline['pitch']],
                    'rhythm' => ['high' => $rhythm, 'baseline' => $baseline['rhythm']]
                ]
            ];
            $homeworkId = $homework['id'];
            if(array_key_exists($homeworkId, $temp)){
                array_push($temp[$homeworkId]['tasks'], $task);
            }else{
                $temp[$homeworkId] = [
                    'teacher_name' => $homework['teacher_name'],
                    'start_time' => $homework['created_time'],
                    'end_time' => $homework['end_time'],
                    'homework_id' => $homework['id'],
                    'tasks' => [$task]
                ];
            }
        }

        $returnData = [];
        foreach ($temp as $k=>$v){
            if(!empty($v)){
                array_push($returnData, $v);
            }
        }

        return $response->withJson(['code'=>0, 'data'=>['homework'=>$returnData]], StatusCode::HTTP_OK);
    }

}