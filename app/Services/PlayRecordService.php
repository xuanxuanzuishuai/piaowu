<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/24
 * Time: 12:23
 */

namespace App\Services;


use App\Models\PlayRecordModel;
use App\Libs\OpernCenter;


class PlayRecordService
{

    /** 学生练琴报告
     * @param $student_id
     * @param $start_time
     * @param $end_time
     * @return array
     */
    public static function getRecordReport($student_id, $start_time, $end_time) {
        $result = PlayRecordModel::getPlayRecordReport($student_id, $start_time, $end_time);

        $ret = [];
        if (empty($result)) {
            return [];
        }
        foreach ($result as $value) {
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
                "lesson_name" => $value["lesson_name"],
                "collection_name" => $value["collection_name"]
            ];
        }

        $max_duration = 0;
        $max_duration_index = null;
        for ($i = 0; $i < count($statistics); $i++) {
            $cur_duration = $statistics[$i]["duration"];
            $cur_lesson_id = $statistics[$i]["lesson_id"];
            if ($cur_duration > $max_duration) {
                $max_duration = $cur_duration;
                $max_duration_index = $i;
            }

            $statistics[$i]["lesson_name"] = $lesson_info[$cur_lesson_id]["lesson_name"];
            $statistics[$i]["collection_name"] = $lesson_info[$cur_lesson_id]["collection_name"];
        }

        $max_duration_lesson = $statistics[$max_duration_index];
        array_push($max_duration_lesson["tags"], "时间最长");

        if ($max_duration_index != null and $max_duration_index > 0 and $max_duration_index != 1) {
            $tmp_lesson = $statistics[1];
            $statistics[1] = $max_duration_lesson;
            $statistics[$max_duration_index] = $tmp_lesson;
        }

        return $statistics;

    }

    /**
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
        return self::getRecordReport($student_id, $start_time, $end_time);
    }
}
