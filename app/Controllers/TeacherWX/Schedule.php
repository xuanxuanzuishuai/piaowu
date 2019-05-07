<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/5/7
 * Time: 11:24
 */

namespace App\Controllers\TeacherWX;


use App\Controllers\ControllerBase;
use App\Libs\Valid;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Models\ScheduleModelForApp;
use App\Models\ScheduleExtendModel;

class Schedule extends ControllerBase
{
    /**
     * 获取上课记录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function scheduleList(Request $request, Response $response){
        $rules = [
            [
                'key' => 'page',
                'type' => 'integer',
                'error_code' => 'page_must_be_integer'
            ],
            [
                'key' => 'limit',
                'type' => 'integer',
                'error_code' => 'limit_must_be_integer'
            ],
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        if (empty($params["page"])){
            $params["page"] = 1;
        }

        if (empty($params["limit"])){
            $params["limit"] = 10;
        }
        $user_id = $this->ci['user_info']['user_id'];
        $data = ScheduleExtendModel::getList([
            "student_id" => $params["student_id"],
            "schedule_status" => ScheduleModelForApp::STATUS_FINISH,
            "teacher_id" => $user_id
        ], $params["page"], $params["limit"]);
        $length = sizeof($data);
        for ($i=0; $i < $length; $i++){
            $detail_score = json_decode($data[$i]["detail_score"], true);;
            $homework_rank = $detail_score["homework_rank"];
            $performance_rank = $detail_score["performance_rank"];
            $data[$i]["homework_remark"] = ScheduleExtendModel::$homework_score_map[$homework_rank];
            $data[$i]["performance_remark"] = ScheduleExtendModel::$performance_score_map[$performance_rank];
        }
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data
        ], StatusCode::HTTP_OK);
    }

}