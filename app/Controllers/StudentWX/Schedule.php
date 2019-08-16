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
use App\Libs\SimpleLogger;
use App\Libs\AliOSS;
use App\Libs\Valid;
use App\Models\ScheduleModelForApp;
use App\Services\HomeworkService;
use App\Services\OpernService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Models\ScheduleExtendModel;
use App\Models\TeacherNoteModel;


class Schedule extends ControllerBase
{

    /**
     * 上课报告
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
        // 本节课信息
        $schedule_extend = ScheduleExtendModel::getUserScheduleExtendDetail($params["schedule_id"], $user_id);
        if (empty($schedule_extend)) {
            $data = [];
        } else {
            $schedule_info = $schedule_extend[0];
            $lesson_ids = $schedule_info["opn_lessons"];
            $detail_score = json_decode($schedule_info["detail_score"], true);
            $homework_rank = $detail_score["homework_rank"];
            $performance_rank = $detail_score["performance_rank"];

            if (!empty($lesson_ids)){
                $opn = new OpernCenter(OpernCenter::PRO_ID_AI_TEACHER, OpernCenter::version);
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

            $note_id_list = [];
            $lesson_2_notes = [];
            $ali = new AliOSS();
            foreach ($homework_list as $homework){
                $homework_audio = $ali->signUrls($homework['homework_audio']);
                array_push($home_work_lesson_info, [
                    "lesson_id"=> $homework["lesson_id"],
                    "collection_id" => $homework["collection_id"],
                    "note_list" => [],
                    'homework_audio' => $homework_audio,
                    'audio_duration' => $homework['audio_duration']
                ]);
                $finish_time = $homework["end_time"];
                $note_ids = explode(",", $homework["note_ids"]);
                if (!empty($homework["note_ids"])){
                    $note_id_list = array_merge($note_id_list, $note_ids);
                    $lesson_2_notes[$homework["lesson_id"]] = $note_ids;
                }
            }

            $note_info_list = TeacherNoteModel::getNotesByIds($note_id_list);

            $note_dic = [];
            foreach ($note_info_list as $note){
                $note_dic[$note["id"]] = $note;
            }

            $length = sizeof($home_work_lesson_info);
            for($i=0; $i < $length; $i++){
                $lesson_info = $home_work_lesson_info[$i];
                $lesson_notes = $lesson_2_notes[$lesson_info["lesson_id"]];
                if (!empty($lesson_notes)){
                    foreach ($lesson_notes as $note_id){
                        $note_info = $note_dic[$note_id];
                        if (!empty($note_info)){
                            $content = json_decode($note_info["content"], true);
                            $image_url = $ali->signUrls($content['coverFile']);
                            array_push($home_work_lesson_info[$i]["note_list"], $image_url);
                        }
                    }
                }
            }

            $home_work_lesson_info = OpernService::formatLessonAndCollectionName($home_work_lesson_info);

            $data["homework"] = $home_work_lesson_info;
            $data["remark"] = $schedule_info["remark"];
            $data["date"] = date("Y年m月d日", $schedule_info["start_time"]);
            $data["class_score"] = $schedule_info["class_score"];
            $data["homework_end_date"] = date("Y年m月d日", $finish_time) ?? "";
            $data["teacher_name"] = $schedule_info["teacher_name"];
            $data["homework_remark"] = ScheduleExtendModel::$homework_score_map[$homework_rank];
            $data["performance_remark"] = ScheduleExtendModel::$performance_score_map[$performance_rank];
            $data["student_name"] = $schedule_info["student_name"];
            $audio_url = $ali->signUrls($schedule_info['audio_comment']);
            $data['audio_comment'] = $audio_url;
            $data['audio_duration'] = $schedule_info['audio_duration'];
            $data['org_name'] = $schedule_info['org_name'];
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data
        ], StatusCode::HTTP_OK);
    }

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
            "student_id" => $user_id,
            "schedule_status" => ScheduleModelForApp::STATUS_FINISH
        ], $params["page"], $params["limit"]);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data
        ], StatusCode::HTTP_OK);
    }
}
