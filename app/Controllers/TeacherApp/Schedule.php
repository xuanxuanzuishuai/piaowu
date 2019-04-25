<?php
/**
 * Created by PhpStorm.
 * User: dahua
 * Date: 2019/4/22
 * Time: 15:28
 */

namespace App\Controllers\TeacherApp;

use App\Controllers\ControllerBase;
use App\Libs\MysqlDB;
use App\Libs\Valid;
use App\Services\HomeworkService;
use App\Services\ScheduleServiceForApp;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Schedule extends ControllerBase
{
    public function end(Request $request, Response $response){
        // 验证请求参数
        $rules = [
            [
                'key' => 'data',
                'type' => 'required',
                'play_data_is_required' => 'play_data_is_required'
            ]
        ];
        $param = $request->getParams();
        $result = Valid::validate($param, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $param = $param['data'];

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        // 结束上课
        list($scheduleEndError, $scheduleId) = ScheduleServiceForApp::endSchedule($param);
        if($scheduleEndError){
            $result = Valid::addAppErrors([], $scheduleEndError);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        // 写入课后作业
        foreach ($param['homework'] as $homework){
            HomeworkService::createHomework(
                $scheduleId,
                $param['org_id'],
                $param['teacher_id'],
                $param['student_id'],
                $homework['limited_days'],
                $homework['remark'],
                $homework['tasks']
            );
        }
        $db->commit();
        return $response->withJson(['code'=>0], StatusCode::HTTP_OK);
    }

}