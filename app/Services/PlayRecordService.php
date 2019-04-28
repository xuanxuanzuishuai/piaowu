<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/24
 * Time: 12:23
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Models\PlayRecordModel;
use App\Libs\OpernCenter;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
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

    /** 解析jwt获取信息
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

    /** 学生练琴报告
     * @param $student_id
     * @param $start_time
     * @param $end_time
     * @return array
     */
    public static function getRecordReport($student_id, $start_time, $end_time) {
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

        // 根据分数由高到低排序
        foreach ($statistics as $key => $row) {
            $score[$key] = $row['max_score'];
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

            array_push($statistics[0]["tags"], "得分最高");
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
        for ($i = 0; $i < count($statistics); $i++) {
            $result["lesson_count"] += 1;
            $cur_duration = $statistics[$i]["duration"];
            $sum_duration += $cur_duration;
            $cur_lesson_id = $statistics[$i]["lesson_id"];
            if ($cur_duration > $max_duration) {
                $max_duration = $cur_duration;
                $max_duration_index = $i;
            }

            $statistics[$i]["lesson_name"] = $lesson_info[$cur_lesson_id]["lesson_name"];
            $statistics[$i]["collection_name"] = $lesson_info[$cur_lesson_id]["collection_name"];
            $ai_record_info = PlayRecordModel::getWonderfulAIRecordId($cur_lesson_id, $student_id, $start_time, $end_time);
            if (!empty($ai_record_info and $ai_record_info["score"] >= 90)){
                $statistics[$i]["ai_record_id"] = $ai_record_info["ai_record_id"];
            }
        }

        array_push($statistics[$max_duration_index]["tags"], "时间最长");
        $max_duration_lesson = $statistics[$max_duration_index];
        if ($max_duration_index != null and $max_duration_index > 0 and $max_duration_index != 1) {
            $tmp_lesson = $statistics[1];
            $statistics[1] = $max_duration_lesson;
            $statistics[$max_duration_index] = $tmp_lesson;
        }

        $result["report_list"] = $statistics;
        $result["duration"] = $sum_duration;
        return $result;

    }

    /** 学生练琴日报
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
        $result = self::getRecordReport($student_id, $start_time, $end_time);
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
        }
        return [$records, $total];
    }
}
