<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/24
 * Time: 12:23
 */

namespace App\Services;


use App\Libs\AIPLCenter;
use App\Libs\Constants;
use App\Libs\Util;
use App\Models\HomeworkTaskModel;
use App\Models\PlayRecordModel;
use App\Libs\OpernCenter;
use App\Models\ReviewCourseModel;
use App\Models\ReviewCourseTaskModel;
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
     * @return array
     */
    public static function getRecordReport($student_id, $start_time, $end_time)
    {
        $records = PlayRecordModel::getPlayRecordReport($student_id, $start_time, $end_time, true);
        $student_info = StudentModelForApp::getStudentInfo($student_id, '');
        $result = [
            'name' => $student_info['name'],
            'duration' => 0,
            'lesson_count' => 0,
            'max_score' => 0,
            'report_list' => []
        ];
        if (empty($records)) {
            return $result;
        }

        $ret = [];
        $max_duration = 0;
        $max_score = 0;
        $sum_duration = 0;
        foreach ($records as $value) {
            $lesson_id = $value['lesson_id'];
            if (!isset($ret[$lesson_id])) {
                $ret[$lesson_id] = [
                    'max_score' => max($value['max_dmc'], $value['max_ai']),
                    'duration' => $value['duration'],
                    'class_duration' => $value['class_duration'],
                    'sub_count' => $value['sub_count'],
                    'dmc_count' => $value['dmc'],
                    'ai_count' => $value['ai'],
                    'part_count' => $value['part'],
                    'max_dmc_score' => $value['max_dmc'],
                    'max_ai_score' => $value['max_ai'],
                    'lesson_id' => $lesson_id,
                    'ai_record_id' => null,
                    'tags' => [],
                ];
            } else {
                $ret[$lesson_id]['max_score'] = max($value['max_dmc'], $value['max_ai'], $ret[$lesson_id]['max_score']);
                $ret[$lesson_id]['duration'] += $value['duration'];
                $ret[$lesson_id]['class_duration'] += $value['class_duration'];
                $ret[$lesson_id]['sub_count'] += $value['sub_count'];
                $ret[$lesson_id]['dmc_count'] += $value['dmc'];
                $ret[$lesson_id]['ai_count'] += $value['ai'];
                $ret[$lesson_id]['part_count'] += $value['part'];
                $ret[$lesson_id]['max_dmc_score'] = max($value['max_dmc'], $ret[$lesson_id]['max_dmc_score']);
                $ret[$lesson_id]['max_ai_score'] = max($value['max_ai'], $ret[$lesson_id]['max_ai_score']);
            }

            $sum_duration += $value['duration'];
            $sum_duration += $value['class_duration'];

            if ($ret[$lesson_id]['max_score'] > $max_score) {
                $max_score = $ret[$lesson_id]['max_score'];
            }

            if ($ret[$lesson_id]['duration'] > $max_duration) {
                $max_duration = $ret[$lesson_id]['duration'];
            }
        }
        $lesson_ids = array_keys($ret);
        $statistics = array_values($ret);

        $lesson_list = [];
        // 获取lesson的信息
        if (!empty($lesson_ids)) {
            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, '1.4');
            $res = $opn->lessonsByIds($lesson_ids);
            if (!empty($res['code']) && $res['code'] !== Valid::CODE_SUCCESS) {
                $lesson_list = [];
            } else {
                $lesson_list = $res['data'];
            }
        }
        $lesson_info = [];
        foreach ($lesson_list as $value) {
            $lesson_info[$value['lesson_id']] = [
                'lesson_name' => $value['opern_name'],
                'collection_name' => $value['collection_name']
            ];
        }

        // 作业数据
        $tasks = HomeworkTaskModel::getTasks($student_id, $lesson_ids, $start_time, $end_time);
        // 保存lesson_id和task_id的映射关系
        $lesson_2_task_map = [];
        foreach ($tasks as $value) {
            $lesson_2_task_map[$value["lesson_id"]] = $value["id"];
        }

        $max_score_records = [];
        for ($i = 0; $i < count($lesson_ids); $i++) {
            $result['lesson_count'] += 1;
            $cur_lesson_id = $statistics[$i]['lesson_id'];

            // 作业数据
            $statistics[$i]['task_id'] = null;
            if (array_key_exists($cur_lesson_id, $lesson_2_task_map)) {
                $statistics[$i]['task_id'] = $lesson_2_task_map[$cur_lesson_id];
            }

            $statistics[$i]['lesson_name'] = $lesson_info[$cur_lesson_id]['lesson_name'];
            $statistics[$i]['collection_name'] = $lesson_info[$cur_lesson_id]['collection_name'];
            $ai_record_info = PlayRecordModel::getWonderfulAIRecordId($cur_lesson_id, $student_id, $start_time, $end_time);
            if (!empty($ai_record_info)) {
                $statistics[$i]['ai_record_id'] = $ai_record_info['ai_record_id'];
            }

            if ($statistics[$i]['max_score'] == $max_score) {
                $max_score_records[] = $statistics[$i];
                unset($statistics[$i]);
            }
        }

        // 按 上课时长，练习时长 排序
        usort($statistics, function ($a, $b) {
            if ($a['class_duration'] == $b['class_duration']) {
                if ($a['duration'] == $b['duration']) {
                    return 0;
                } else {
                    return ($a['duration'] < $b['duration']) ? 1 : -1;
                }
            } else {
                return ($a['class_duration'] < $b['class_duration']) ? 1 : -1;
            }
        });

        // 排序: 1.得分最高，2.上课、练习间长顺序
        $playRecords = array_merge($max_score_records, $statistics);

        $result['max_score'] = $max_score;
        $result['report_list'] = $playRecords;
        $result['duration'] = $sum_duration;
        return $result;
    }

    /**
     * 学生练琴日报
     * @param $student_id
     * @param null $date
     * @return array
     */
    public static function getDayRecordReport($student_id, $date = null)
    {
        if (empty($date)) {
            $date = "today";
        }
        $start_time = strtotime($date);
        $end_time = $start_time + 86399;
        $result = self::getRecordReport($student_id, $start_time, $end_time);
        $result["jwt"] = self::getShareReportToken($student_id, $date);
        $result['token'] = AIBackendService::genStudentToken($student_id);
        $result["date"] = date("Y年m月d日", $start_time);
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
            $item['score'] = Util::convertToIntIfCan($item['score']);
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
        if(empty($myself) && (!StudentModelForApp::isAnonymousStudentId($studentId))) {
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

    /**
     * 学生练琴日报(小叶子陪练)
     * @param $student_id
     * @param null $date
     * @param bool $is_cut
     * @return array
     */
    public static function getDayRecordReportPanda($student_id, $date = null, $is_cut = false)
    {
        if (empty($date)) {
            $date = "today";
        }
        $start_time = strtotime($date);
        $end_time = $start_time + 86399;
        $result = self::getRecordReportPanda($student_id, $start_time, $end_time, $is_cut);
        $result["jwt"] = self::getShareReportToken($student_id, $date);
        $result['token'] = AIBackendService::genStudentToken($student_id);
        $result["date"] = date("Y年m月d日", $start_time);
        return $result;
    }

    public static function getRecordReportPanda($student_id, $start_time, $end_time, $is_cut)
    {
        $records = PlayRecordModel::getPlayRecordReport($student_id, $start_time, $end_time, true);
        $student_info = StudentModelForApp::getStudentInfo($student_id, '');
        $result = [
            'name' => $student_info['name'],
            'duration' => 0,
            'lesson_count' => 0,
            'max_score' => 0,
            'report_list' => []
        ];
        if (empty($records)) {
            return $result;
        }

        $ret = [];
        $max_duration = 0;
        $max_score = 0;
        $sum_duration = 0;
        foreach ($records as $value) {
            $lesson_id = $value['lesson_id'];
            if (!isset($ret[$lesson_id])) {
                $ret[$lesson_id] = [
                    'max_score' => max($value['max_dmc'], $value['max_ai']),
                    'duration' => $value['duration'],
                    'class_duration' => $value['class_duration'],
                    'sub_count' => $value['sub_count'],
                    'dmc_count' => $value['dmc'],
                    'ai_count' => $value['ai'],
                    'part_count' => $value['part'],
                    'max_dmc_score' => $value['max_dmc'],
                    'max_ai_score' => $value['max_ai'],
                    'lesson_id' => $lesson_id,
                    'ai_record_id' => null,
                    'tags' => [],
                ];
            } else {
                $ret[$lesson_id]['max_score'] = max($value['max_dmc'], $value['max_ai'], $ret[$lesson_id]['max_score']);
                $ret[$lesson_id]['duration'] += $value['duration'];
                $ret[$lesson_id]['class_duration'] += $value['class_duration'];
                $ret[$lesson_id]['sub_count'] += $value['sub_count'];
                $ret[$lesson_id]['dmc_count'] += $value['dmc'];
                $ret[$lesson_id]['ai_count'] += $value['ai'];
                $ret[$lesson_id]['part_count'] += $value['part'];
                $ret[$lesson_id]['max_dmc_score'] = max($value['max_dmc'], $ret[$lesson_id]['max_dmc_score']);
                $ret[$lesson_id]['max_ai_score'] = max($value['max_ai'], $ret[$lesson_id]['max_ai_score']);
            }

            $sum_duration += $value['duration'];
            $sum_duration += $value['class_duration'];

            if ($ret[$lesson_id]['max_score'] > $max_score) {
                $max_score = $ret[$lesson_id]['max_score'];
            }

            if ($ret[$lesson_id]['duration'] > $max_duration) {
                $max_duration = $ret[$lesson_id]['duration'];
            }
        }
        $lesson_ids = array_keys($ret);
        $statistics = array_values($ret);

        $lesson_list = [];
        // 获取lesson的信息
        if (!empty($lesson_ids)) {
            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, '1.4');
            $res = $opn->lessonsByIds($lesson_ids);
            if (!empty($res['code']) && $res['code'] !== Valid::CODE_SUCCESS) {
                $lesson_list = [];
            } else {
                $lesson_list = $res['data'];
            }

        }
        $lesson_info = [];
        foreach ($lesson_list as $value) {
            $lesson_info[$value['lesson_id']] = [
                'lesson_name' => $value['opern_name'],
                'collection_name' => $value['collection_name']
            ];
        }

        $max_score_records = [];
        for ($i = 0; $i < count($lesson_ids); $i++) {
            $result["lesson_count"] += 1;
            $cur_lesson_id = $statistics[$i]["lesson_id"];

            $statistics[$i]["lesson_name"] = $lesson_info[$cur_lesson_id]["lesson_name"];
            $statistics[$i]["collection_name"] = $lesson_info[$cur_lesson_id]["collection_name"];
            $ai_record_info = PlayRecordModel::getWonderfulAIRecordId($cur_lesson_id, $student_id, $start_time, $end_time);
            if (!empty($ai_record_info)) {
                $statistics[$i]["ai_record_id"] = $ai_record_info["ai_record_id"];
            }

            if ($statistics[$i]["max_score"] == $max_score) {
                $max_score_records[] = $statistics[$i];
                unset($statistics[$i]);
            }
        }

        // 按 上课时长，练习时长 排序
        usort($statistics, function ($a, $b) {
            if ($a['class_duration'] == $b['class_duration']) {
                if ($a['duration'] == $b['duration']) {
                    return 0;
                } else {
                    return ($a['duration'] < $b['duration']) ? 1 : -1;
                }
            } else {
                return ($a['class_duration'] < $b['class_duration']) ? 1 : -1;
            }
        });

        if ($is_cut) {
            // 只展示得分最高和时间最长
            $maxDurationLesson = $statistics[0] ?? [];
            $playRecords = array_merge($max_score_records, [$maxDurationLesson]);
        } else {
            // 排序: 1.得分最高，2.上课、练习间长顺序
            $playRecords = array_merge($max_score_records, $statistics);
        }

        $result["max_score"] = $max_score;
        $result["report_list"] = $playRecords;
        $result["duration"] = $sum_duration;
        return $result;
    }

    /**
     * 获取某天练琴的学生uuid
     * @param $date
     * @return array
     */
    public static function getDayPlayedStudents($date)
    {
        $date = strtotime($date);
        return PlayRecordModel::getDayPlayedStudents($date);
    }

    /**
     * 获取学生练琴日历
     * @param $studentId
     * @param $year
     * @param $month
     * @return array
     */
    public static function getPlayCalendar($studentId, $year, $month)
    {
        $startTime = strtotime($year . "-" . $month);
        $endTime = strtotime('+1 month', $startTime) - 1;
        $monthSum = PlayRecordModel::getStudentSumByDate($studentId, $startTime, $endTime);

        $where = [
            'play_date[>=]' => date('Ymd', $startTime),
            'play_date[<=]' => date('Ymd', $endTime),
            's.id' => $studentId,
            's.has_review_course' => [ReviewCourseModel::REVIEW_COURSE_49, ReviewCourseModel::REVIEW_COURSE_1980],
            'rct.status' => [ReviewCourseTaskModel::STATUS_SEND_SUCCESS, ReviewCourseTaskModel::STATUS_SEND_FAILURE],
        ];
        list($total, $tasks) = ReviewCourseTaskModel::getTasks($where);
        if ($total > 0) {
            $tasks = array_column($tasks, 'id', 'play_date');
        }

        foreach ($monthSum as $i => $daySum) {
            $monthSum[$i]['review_task_id'] = $tasks[$daySum['play_date']] ?? 0;
        }

        return $monthSum;
    }
}
