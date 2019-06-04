<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/24
 * Time: 12:23
 */

namespace App\Services;


use App\Controllers\Student\PlayRecord;
use App\Libs\AIPLCenter;
use App\Libs\Constants;
use App\Libs\Util;
use App\Models\PlayRecordModel;
use App\Libs\OpernCenter;
use App\Models\StudentModel;
use App\Models\StudentOrgModel;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use App\Libs\Valid;
use App\Models\StudentModelForApp;


class PlayRecordService
{
    const signKey = "wblMloJrdkUwIxVLchlXB9Unvr68dJo";

    public static function getShareReportToken($student_id, $date) {
        $builder = new Builder();
        $signer = new Sha256();
        $builder->set("student_id", $student_id);
        $builder->set("date", $date);
        $builder->sign($signer, self::signKey);
        $token = $builder->getToken();
        return (string)$token;
    }

    /**
     * 解析jwt获取信息
     * @param $token
     * @return array
     */
    public static function parseShareReportToken($token){
        $parse = (new Parser())->parse((string)$token);
        $signer = new Sha256();
        if (!$parse->verify($signer,self::signKey)) {
            return ["code" => 1];
        };
        $student_id = $parse->getClaim("student_id");
        $date = $parse->getClaim("date");
        return ["student_id" => $student_id, "date" => $date, "code" => 0];
    }

    /**
     * 学生练琴报告
     * @param $student_id
     * @param $start_time
     * @param $end_time
     * @param $ai_record_id
     * @param $need_cut
     * @return array
     */
    public static function getRecordReport($student_id, $start_time, $end_time, $ai_record_id=true, $need_cut=false) {
        $records = PlayRecordModel::getPlayRecordReport($student_id, $start_time, $end_time);
        $student_info = StudentModelForApp::getStudentInfo($student_id, "");
        $result = [
            "name" => $student_info["name"],
            "duration" => 0,
            "lesson_count" => 0,
            "max_score" => 0,
            "report_list" => []
        ];
        $ret = [];
        if (empty($records)) {
            return $result;
        }
        foreach ($records as $value) {
            $lesson_id = $value["lesson_id"];
            if (!isset($ret[$lesson_id])) {
                $ret[$lesson_id] = [
                    "max_score" => max($value["max_dmc"], $value["max_ai"]),
                    "duration" => $value["duration"],
                    "sub_count" => $value["sub_count"],
                    "dmc_count" => $value["dmc"],
                    "ai_count" => $value["ai"],
                    "max_dmc_score" => $value["max_dmc"],
                    "max_ai_score" => $value["max_ai"],
                    "lesson_id" => $lesson_id,
                    "ai_record_id" => null,
                    "tags" => [],
                ];
            } else {
                $ret[$lesson_id]["max_score"] = max($value["max_dmc"], $value["max_ai"], $ret[$lesson_id]["max_score"]);
                $ret[$lesson_id]["duration"] += $value["duration"];
                $ret[$lesson_id]["sub_count"] += $value["sub_count"];
                $ret[$lesson_id]["dmc_count"] += $value["dmc"];
                $ret[$lesson_id]["ai_count"] += $value["ai"];
                $ret[$lesson_id]["max_dmc_score"] = max($value["max_dmc"], $ret[$lesson_id]["max_dmc_score"]);
                $ret[$lesson_id]["max_ai_score"] = max($value["max_ai"], $ret[$lesson_id]["max_ai_score"]);
            }
        }
        $lesson_ids = array_keys($ret);
        $statistics = array_values($ret);

        // 根据分数由高到低排序，相同分数以练琴时长由高到低排序
        foreach ($statistics as $key => $row) {
            $score[$key] = $row['max_score'] * ($end_time - $start_time) + $row["duration"];
        }
        array_multisort($score, SORT_DESC, $statistics);
        $result["max_score"] = $statistics[0]["max_score"];

        $lesson_list = [];
        // 获取lesson的信息
        if (!empty($lesson_ids)) {
            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, "1.4");
            $res = $opn->lessonsByIds($lesson_ids);
            if (!empty($res['code']) && $res['code'] !== Valid::CODE_SUCCESS) {
                $lesson_list = [];
            } else {
                $lesson_list = $res["data"];
            }

        }
        $lesson_info = [];
        foreach ($lesson_list as $value){
            $lesson_info[$value["lesson_id"]] = [
                "lesson_name" => $value["opern_name"],
                "collection_name" => $value["collection_name"]
            ];
        }

        $sum_duration = 0;
        $max_duration = 0;
        $max_duration_index = null;
        $max_score_index = null;
        for ($i = 0; $i < count($statistics); $i++) {
            $result["lesson_count"] += 1;
            $cur_duration = $statistics[$i]["duration"];
            $sum_duration += $cur_duration;
            $cur_lesson_id = $statistics[$i]["lesson_id"];
            if ($cur_duration > $max_duration) {
                $max_duration = $cur_duration;
                $max_duration_index = $i;
            }

            if ($statistics[$i]["max_score"] == $result["max_score"]){
                array_push($statistics[$i]["tags"], "得分最高");
                $max_score_index = $i;
            }

            $statistics[$i]["lesson_name"] = $lesson_info[$cur_lesson_id]["lesson_name"];
            $statistics[$i]["collection_name"] = $lesson_info[$cur_lesson_id]["collection_name"];
            if ($ai_record_id){
                $ai_record_info = PlayRecordModel::getWonderfulAIRecordId($cur_lesson_id, $student_id, $start_time, $end_time);
                if (!empty($ai_record_info and $ai_record_info["score"] >= 90)){
                    $statistics[$i]["ai_record_id"] = $ai_record_info["ai_record_id"];
                }
            }
        }

        array_push($statistics[$max_duration_index]["tags"], "时间最长");

        if ($need_cut){
            if ($max_duration_index > $max_score_index){
                $max_duration_statistics = $statistics[$max_duration_index];
                $statistics = array_slice($statistics,0,$max_score_index+1);
                array_push($statistics, $max_duration_statistics);
            } else {
                $statistics = array_slice($statistics,0,$max_score_index+1);
            }
        }

        $result["report_list"] = $statistics;
        $result["duration"] = $sum_duration;
        return $result;
    }

    public static function getPlayRecordStatistic($student_id, $start_time, $end_time){
        $report = self::getRecordReport($student_id, $start_time, $end_time, true);
        $statistics = $report["report_list"];
        $task_lessons = PlayRecordModel::getHomeworkByPlayRecord($student_id, $start_time, $end_time);

        // 保存lesson_id和task_id的映射关系
        $lesson_2_task_map = [];
        foreach ($task_lessons as $value){
            $lesson_2_task_map[$value["lesson_id"]] = $value["task_id"];
        }

        // 有作业的优先排序
        $homework_play_list = [];
        $no_homework_play_list = [];
        foreach ($statistics as $value){
            $lesson_id = $value["lesson_id"];
            if (array_key_exists($lesson_id, $lesson_2_task_map)){
                $value["task_id"] = $lesson_2_task_map[$lesson_id];
                array_push($homework_play_list, $value);
            } else {
                array_push($no_homework_play_list, $value);
            }
        }

        $report["report_list"] = array_merge($homework_play_list, $no_homework_play_list);
        return $report;
    }

    /**
     * 每日练琴记录
     * @param $student_id
     * @param null $date
     * @return array
     */
    public static function getDayPlayRecordStatistic($student_id, $date=null){
        if (empty($date)){
            $date = "today";
        }
        $start_time = strtotime($date);
        $end_time = $start_time + 86399;
        $result = self::getPlayRecordStatistic($student_id, $start_time, $end_time);
        $result["date"] = date("Y年m月d日", $start_time);
        return $result;
    }

    /**
     * 学生练琴日报
     * @param $student_id
     * @param null $date
     * @return array
     */
    public static function getDayRecordReport($student_id, $date=null){
        if (empty($date)){
            $date = "today";
        }
        $start_time = strtotime($date);
        $end_time = $start_time + 86399;
        $result = self::getRecordReport($student_id, $start_time, $end_time, true, true);
        $token = self::getShareReportToken($student_id, $date);
        $result["date"] = date("Y年m月d日", $start_time);
        $result["jwt"] = $token;
        return $result;
    }

    /**
     * 查询指定机构日报
     * 指定学生id时查询指定学生日报
     * 否则查询所有学生日报
     * @param $orgId
     * @param $studentId
     * @param $startTime
     * @param $endTime
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function selectReport($orgId, $studentId, $startTime, $endTime, $page, $count, $params)
    {
        list($records, $total) = PlayRecordModel::selectReport($orgId, $studentId, $startTime, $endTime, $page, $count,$params);
        foreach($records as &$r) {
            $r['max_score'] = max($r['max_dmc'], $r['max_ai']);
            $r['lesson_type'] = DictService::getKeyValue(Constants::DICT_TYPE_PLAY_RECORD_LESSON_TYPE, $r['lesson_type']);
            //alias
            $r['create_time'] = $r['created_time'];
            $r['duration'] = Util::formatExerciseTime($r['duration']);
        }
        return [$records, $total];
    }

    /**
     * 格式化练习记录
     * @param $play_record
     * @return array
     */
    public static function formatLessonTestStatistics($play_record){
        $format_record = [];
        $max_score_index_map = [];
        foreach ($play_record as $item) {
            $create_date = date("Y-m-d", $item["created_time"]);

            if ($item["complete"]){
                $item["tags"] = ["达成要求"];
            } else{
                $item["tags"] = [];
            }

            $item["created_time"] = date("Y-m-d H:i", $item["created_time"]);

            if(array_key_exists($create_date, $format_record)){
                // 更新最大得分index
                if ($item["score"] > $format_record[$create_date]["max_score"]){
                    $max_score_index_map[$create_date] = sizeof($format_record[$create_date]['records']);
                    $format_record[$create_date]["max_score"] = $item["score"];
                }
                array_push($format_record[$create_date]['records'], $item);
            }else{
                $format_record[$create_date] = [
                    'create_date' => $create_date,
                    'records' => [$item],
                    'max_score' => $item["score"]
                ];
                $max_score_index_map[$create_date] = 0;
            }
        }
        foreach ($max_score_index_map as $date => $index){
            array_push($format_record[$date]["records"][$index]["tags"], "当日最高");
        }
        return array_values($format_record);
    }

    /**
     * 获取某个ai_record_id对应的陪练数据
     * @param $student_id
     * @param $ai_record_id
     * @return array|bool|mixed
     */
    public static function getAiAudio($student_id, $ai_record_id){
        if (empty($ai_record_id)){
            return [];
        }
        $playInfo = PlayRecordModel::getRecord(["student_id" => $student_id, "ai_record_id" => $ai_record_id]);
        if (empty($playInfo)){
            return [];
        }
        $data = AIPLCenter::userAudio($ai_record_id);
        return $data;
    }

    /**
     * 获取测评得分详情
     * @param $student_id
     * @param $ai_record_id
     * @return array
     */
    public static function getAIRecordGrade($student_id, $ai_record_id){
        if (empty($ai_record_id)){
            return [];
        }
        $search_sql = ["ai_record_id" => $ai_record_id];
        if (!empty($student_id)){
            $search_sql["student_id"] = $student_id;
        }
        $playInfo = PlayRecordModel::getRecord($search_sql);
        if (empty($playInfo)){
            return [];
        }

        $lesson_id = $playInfo["lesson_id"];
        $data = AIPLCenter::recordGrade($ai_record_id);

        if (empty($data) or $data["meta"]["code"] != 0){
            $score_ret = [];
        } else {
            $score = $data["data"]["score"];
            $score_ret = [
                "simple_complete" => $score["simple_complete"],
                "simple_final" => $score["simple_final"],
                "simple_pitch" => $score["simple_pitch"],
                "simple_rhythm" => $score["simple_rhythm"],
                "simple_speed_average" => $score["simple_speed_average"],
                "simple_speed" => $score["simple_speed"]
            ];
        }
        $data = AIPLCenter::userAudio($ai_record_id);
        if (empty($data) or $data["meta"]["code"] != 0){
            $wonderful_url = "";
        } else {
            $wonderful_url = $data["data"]["audio_url"];
        }

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, OpernCenter::version);
        $res = $opn->lessonsByIds([$lesson_id]);
        if (!empty($res['code']) && $res['code'] !== Valid::CODE_SUCCESS) {
            $opern_list = [];
        } else {
            $opern_id = $res["data"][0]["opern_id"];
            $result = $opn->staticResource($opern_id, 'png');
            if (!empty($result['code']) && $result['code'] !== Valid::CODE_SUCCESS){
                $opern_list = [];
            } else {
                $opern_list = $result["data"];
            }
        }
        return [
            "score" => $score_ret,
            "wonderful_url" => $wonderful_url,
            "opern_list" => $opern_list
        ];
    }

    public static function getRanks($studentId, $lessonId, $isOrg){

        $org = StudentOrgModel::getRecords(['student_id'=>$studentId, 'status'=>1], 'org_id');
        if(!empty($isOrg) and !empty($org)){
            $students = StudentOrgModel::getRecords(['org_id'=>$org, 'status'=>1], 'student_id');
        }else{
            $students = [];
        }
        $ranks = PlayRecordModel::getRank($lessonId, $students);
        $ret = [];
        $myself = [];

        // 处理排名，相同分数具有并列名次
        $prevStudent = null;
        foreach ($ranks as $v){
            if(empty($prevStudent)){
                $v['order'] = 1;
                $prevStudent = $v;
            }else{
                if($v['score'] == $prevStudent['score']){
                    $v['order'] = $prevStudent['order'];
                }else{
                    $v['order'] = $prevStudent['order'] + 1;
                    $prevStudent = $v;
                }
            }
            array_push($ret, $v);
            if($v['student_id'] == $studentId){
                $myself = $v;
            }
        }
        if(empty($myself)){
            $studentBestPlay = PlayRecordModel::getRank($lessonId, [$studentId]);
            if(!empty($studentBestPlay)){
                // 未上榜
                $myself = $studentBestPlay[0];
                $myself['order'] = 0;
            }else{
                // 未演奏
                $student = StudentModel::getById($studentId);
                $myself['name']  = $student['name'];
                $myself['order'] = -1;
            }
        }
        return ['ranks' => $ret, 'myself' => $myself, 'hasOrg' => count($org) > 0];
    }

}
