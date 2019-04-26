<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/26
 * Time: 10:29
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\OpernCenter;
use App\Libs\Valid;
use App\Services\HomeworkService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Models\ScheduleExtendModel;


class Schedule extends ControllerBase
{

    /** 上课报告
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function scheduleReport(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'schedule_id',
                'type' => 'required',
                'error_code' => 'schedule_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $user_id = $this->ci['user_info']['user_id'];
//        $user_id = 22;
        // 本节课信息
        $schedule_extend = ScheduleExtendModel::getUserScheduleExtendDetail($params["schedule_id"], $user_id);
        if (empty($schedule_extend)) {
            $data = [];
        } else {
            $schedule_info = $schedule_extend[0];
            $lesson_ids = $schedule_info["opn_lessons"];
            $class_score = $schedule_info["class_score"];
            $remark = $schedule_info["remark"];
            $start_time = $schedule_info["start_time"];
            $teacher_name = $schedule_info["teacher_name"];

            if (!empty($lesson_ids)){
                $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, OpernCenter::version);
                $res = $opn->lessonsByIds($lesson_ids);
                if (!empty($res['code']) && $res['code'] !== Valid::CODE_SUCCESS) {
                    $lesson_list = [];
                } else {
                    $lesson_list = $res["data"];
                }
                $study_lessons = [];
                foreach ($lesson_list as $lesson) {
                    array_push($study_lessons, [
                        "lesson_id"=> $lesson["lesson_id"],
                        "lesson_name" => $lesson["opern_name"],
                        "collection_id" => $lesson["collection_id"],
                        "collection_name" => $lesson["collection_name"]
                    ]);
                }
                $data["study_lessons"] = $study_lessons;
            }

            $home_work_lesson_info = [];
            $homework_list = HomeworkService::getScheduleHomeWorkList($params["schedule_id"]);
            $finish_time = null;
            foreach ($homework_list as $homework){
                array_push($home_work_lesson_info, [
                    "lesson_id"=> $homework["lesson_id"],
                    "lesson_name" => $homework["lesson_name"],
                    "collection_id" => $homework["collection_id"],
                    "collection_name" => $homework["collection_name"]
                ]);

                $finish_time = $homework["end_time"];
            }

            $data["homework"] = $home_work_lesson_info;
            $data["remark"] = $remark;
            $data["date"] = date("Y年m月d日", $start_time);
            $data["class_score"] = $class_score;
            $data["homework_end_date"] = date("Y年m月d日", $finish_time);
            $data["teacher_name"] = $teacher_name;
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data
        ], StatusCode::HTTP_OK);
    }
}
