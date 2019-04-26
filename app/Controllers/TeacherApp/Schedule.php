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
use App\Libs\UserCenter;
use App\Libs\Valid;
use App\Services\HomeworkService;
use App\Services\ScheduleServiceForApp;
use App\Services\WeChatService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Models\UserWeixinModel;


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
        $date_str = date("Y年m月d日", time());
        $data = [
            'first' => [
                'value' => "您的课后报告已经生成，点击查看。",
                'color' => "#323d83"
            ],
            'keyword1' => [
                'value' => "课后报告",
                'color' => "#323d83"
            ],
            'keyword2' => [
                'value' => $date_str,
                'color' => "#323d83"
            ],
            'remark' => [
                'value' => "点击【详情】查看",
                'color' => "#323d83"
            ]
        ];
        $studentWeChatInfo = UserWeixinModel::getBoundInfoByUserId($param['student_id'],
            UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            WeChatService::USER_TYPE_STUDENT
            );
        if (!empty($studentWeChatInfo)){

            WeChatService::notifyUserWeixinTemplateInfo(
                UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
                WeChatService::USER_TYPE_STUDENT,
                $studentWeChatInfo["open_id"],
                $_ENV["WECHAT_DAILY_RECORD_REPORT"],
                $data,
                $_ENV["WECHAT_FRONT_DOMAIN"] . "/student/report?schedule_id=" . $scheduleId
            );
        }
        return $response->withJson(['code'=>0], StatusCode::HTTP_OK);
    }
}
