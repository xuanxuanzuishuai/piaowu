<?php
/**
 * Created by PhpStorm.
 * User: dahua
 * Date: 2019/4/17
 * Time: 16:40
 * 学生作业相关接口
 */

namespace App\Controllers\StudentApp;
use App\Libs\OpernCenter;
use App\Libs\SimpleLogger;
use App\Controllers\ControllerBase;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\AppVersionModel;
use App\Services\HomeworkService;
use App\Services\OpernService;
use App\Services\AppVersionService;
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
    public function record(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'task_id',
                'type' => 'required',
                'error_code' => 'task_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $userId = $this->ci['student']['id'];
        list($homework, $playRecord) = HomeworkService::getStudentHomeworkPractice($userId,
            $params['task_id'], null, null, null, true);
        if(empty($homework)){
            $errors = Valid::addAppErrors([], "homework_not_found");
            return $response->withJson($errors, StatusCode::HTTP_OK);
        }

        $appVersion = AppVersionService::getPublishVersionCode(
            AppVersionModel::APP_TYPE_STUDENT, AppVersionService::PLAT_ID_IOS);
        $extra = OpernService::getLessonForJoin(
            $homework['lesson_id'],
            OpernCenter::PRO_ID_AI_STUDENT,
            $appVersion,
            $this->ci['opn_auditing'],
            $this->ci['opn_publish']
        );

        // 组装数据
        $returnData = [
            'homework' => [
                'id' => $homework['id'],
                'task_id' => $homework['task_id'],
                'baseline' => json_decode($homework['baseline'], true),
                // 打点字段
                'scoreName' => $extra['opern_id'],
                'scoreId' => $extra['opern_name']
            ],
            'play_record' => [],
        ];
        foreach ($playRecord as $item) {
            if ($item['complete_id'] != null && $item['task_id'] != $params['task_id']){
                continue;
            }
            $temp = [
                'time' => $item['created_time'],
                'score' => Util::floatIsInt($item['score']) ? (int)$item['score'] : $item['score'],
                'complete' => (int)$item['complete'],
                'record_id' => $item['ai_record_id']
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
        $userId = $this->ci['student']['id'];
        $data = HomeworkService::getStudentHomeWorkList($userId);
        $lessonIds = array_column($data, 'lesson_id');
        $extra = OpernService::getLessonForJoin($lessonIds,
            OpernCenter::PRO_ID_AI_STUDENT,
            $this->ci['opn_pro_ver'],
            $this->ci['opn_auditing'],
            $this->ci['opn_publish']
        );

        // 组装数据
        $temp = [];
        foreach ($data as $homework){
            if ($homework['end_time'] < time()){
                continue;
            }
            $baseline = json_decode($homework['baseline'], true);

            // 以homework为单位聚合task
            $homeworkId = $homework['id'];
            $task = [
                'task_id' => $homework['task_id'],
                'lesson_id' => $homework['lesson_id'],
                'complete' => $homework['complete'],
                'score_id' => $extra[$homework['lesson_id']]['opern_id'],
                'lesson_name' => $extra[$homework['lesson_id']]['opern_name'],
                'score_detail' => [
                    'pitch' => ['high' => 0, 'baseline' => $baseline['pitch']],
                    'rhythm' => ['high' => 0, 'baseline' => $baseline['rhythm']]
                ]
            ];
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