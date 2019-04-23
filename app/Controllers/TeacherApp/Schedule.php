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
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\AppConfigModel;
use App\Services\HomeworkService;
use App\Services\ScheduleService;
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

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        // 写入课后作业
        HomeworkService::createHomework(
            $param['schedule_id'],
            $param['$org_id'],
            $param['teacher_id'],
            $param['student_id'],
            $param['homework']['limited_days'],
            $param['homework']['tasks'],
            $param['remark']
        );
        // 写入课后报告
        // 结束上课
        ScheduleService::endSchedule($param);
        $db->commit();

    }

}