<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/3/30
 * Time: 3:32 PM
 */

namespace app\Services;


use App\Libs\OpernCenter;
use App\Libs\Valid;
use App\Models\AIPlayRecordModel;
use App\Models\StudentModel;

class AIPlayRecordService
{
    const UI_ENTRY_OLD = 1; // 旧版
    const UI_ENTRY_TEST = 2; // 测评
    const UI_ENTRY_LEARN = 3; // 识谱
    const UI_ENTRY_IMPROVE = 4; // 提升

    /**
     * 开始
     * @param $studentId
     * @param $params
     * @return int
     */
    public static function start($studentId, $params)
    {
        $now = time();

        $recordData = [
            'lesson_id' => $params['lesson_id'],
            'score_id' => $params['score_id'],
            'record_id' => $params['record_id'],
            'phrase_id' => $params['phrase_id'],
            'practice_mode' => $params['practice_mode'],
            'hand' => $params['hand'],
            'ui_entry' => $params['ui_entry'],
            'input_type' => $params['input_type'],
            'create_time' => $now,

            /* 等待异步更新
            'end_time' => $params['end_time'],
            'duration' => $params['duration'],
            'audio_url' => $params['audio_url'],
            'score_final' => $params['score_final'],
            'score_complete' => $params['score_complete'],
            'score_pitch' => $params['score_pitch'],
            'score_rhythm' => $params['score_rhythm'],
            'score_speed' => $params['score_speed'],
            'score_speed_average' => $params['score_speed_average'],
            */
        ];

        $recordId =  AIPlayRecordModel::insertRecord($recordData);

        if (empty($recordId)) {
            return 0;
        }

        StudentModel::updateRecord($studentId, ['last_play_time' => $now], false);

        return $recordId;
    }


    /**
     * 上课
     * @param $params
     * @return bool
     */
    public static function end($params)
    {
        $record = AIPlayRecordModel::getRecord(['record_id' => $params['record_id']]);
        if (empty($record)) {
            return 0;
        }

        $update = [
            'end_time' => $params['end_time'],
            'duration' => $params['duration'],
            'audio_url' => $params['audio_url'],
            'score_final' => $params['score_final'],
            'score_complete' => $params['score_complete'],
            'score_pitch' => $params['score_pitch'],
            'score_rhythm' => $params['score_rhythm'],
            'score_speed' => $params['score_speed'],
            'score_speed_average' => $params['score_speed_average'],
        ];

        $cnt = AIPlayRecordModel::updateRecord($record['id'], $update, false);

        return $cnt > 0 ? 1 : 0;
    }


    /**
     * 统计当日练琴数据
     * @param $studentId
     * @param $date
     * @return array
     */
    public static function getDailyReportData($studentId, $date)
    {
        $student = StudentModel::getById($studentId);

        $startTime = strtotime($date);
        $endTime = $startTime + 86399;

        $records = AIPlayRecordModel::getRecords([
            'student_id' => $studentId,
            'end_time[<>]' => [$startTime, $endTime]
        ]);

        $result = [
            'name' => $student['name'],
            'duration' => 0,
            'lesson_count' => 0,
            'high_score' => 0,
        ];

        $lessonReports = [];
        $topLessonId = null;

        foreach ($records as $record) {
            $lessonId = $record['lesson_id'];

            if (empty($lessonReports[$lessonId])) {
                $result['lesson_count']++; // 总曲目

                $lessonReports[$lessonId] = [
                    'lesson_id' => $lessonId,
                    'lesson_name' => '',
                    'collection_name' => '',
                    'total_duration' => 0,
                    'part_learn_count' => 0,
                    'part_learn_id_map' => [],
                    'part_improve_count' => 0,
                    'part_improve_id_map' => [],
                    'test_count' => 0,
                    'test_high_score' => 0,
                    'old_mode_duration' => 0,
                    'sort_score' => 0,
                    'best_record_id' => 0,
                ];
            }

            $result['duration'] += $record['duration']; // 总时长
            $lessonReports[$lessonId]['total_duration'] += $record['duration']; // 单课总时长
            $lessonReports[$lessonId]['sort_score'] += $record['duration']; // 时长作为排序索引

            if ($record['ui_entry'] == AIPlayRecordService::UI_ENTRY_OLD) {
                // 怀旧模式总时长
                $lessonReports[$lessonId]['old_mode_duration'] += $record['duration'];
                continue;
            }

            if ($record['ui_entry'] == AIPlayRecordService::UI_ENTRY_LEARN && $record['is_phrase']) {
                // 统计 识谱 的乐句数量
                if (empty($lessonReports[$lessonId]['part_learn_id_map'][$record['phrase_id']])) {
                    $lessonReports[$lessonId]['part_learn_id_map'][$record['phrase_id']] = true;
                    $lessonReports[$lessonId]['part_learn_count']++;
                }
                continue;
            }

            if ($record['ui_entry'] == AIPlayRecordService::UI_ENTRY_IMPROVE && $record['is_phrase']) {
                // 统计 提高 的乐句数量
                if (empty($lessonReports[$lessonId]['part_improve_id_map'][$record['phrase_id']])) {
                    $lessonReports[$lessonId]['part_improve_id_map'][$record['phrase_id']] = true;
                    $lessonReports[$lessonId]['part_improve_count']++;
                }
                continue;
            }

            if ($record['ui_entry'] == AIPlayRecordService::UI_ENTRY_TEST) {
                // 测评总次数
                $lessonReports[$lessonId]['test_count']++;

                // 单课最高分
                $curMaxScore = $lessonReports[$lessonId]['test_high_score'];
                if ($curMaxScore < $record['score_final']) {
                    $lessonReports[$lessonId]['test_high_score'] = $record['score_final'];

                    // 大于90分显示精彩回放
                    $showReplay = false;
                    if ($record['score_final'] >= 90) {
                        $lessonReports[$lessonId]['best_record_id'] = $record['record_id'];
                        $showReplay = true;
                    }

                    // 当日最高分
                    if ($result['high_score'] <  $record['score_final']) {
                        $result['high_score'] =  $record['score_final'];
                        // 顶部展示最高分且大于90分显示到列表头部
                        if ($showReplay) {
                            $topLessonId = $lessonId;
                        }
                    }
                }
                continue;
            }

        }

        // 列表排序
        if (!empty($topLessonId)) {
            $lessonReports[$topLessonId]['sort_score'] += 99999;
        }
        usort($lessonReports, function ($a, $b) {
            return $a['sort_score'] < $b['sort_score'];
        });

        // 获取lesson的信息
        $lessonInfo = [];
        $lessonIds = array_column($lessonReports, 'lesson_id');
        if (!empty($lessonIds)) {
            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, '5.0');
            $res = $opn->lessonsByIds($lessonIds);
            if (!empty($res) && $res['code'] == Valid::CODE_SUCCESS) {
                $data = $res['data'];
                $lessonInfo = array_combine(array_column($data, 'lesson_id'), $data);
            }

        }

        // 生成文本，补充课程名和书名
        foreach ($lessonReports as $idx => $report) {
            $lessonReports[$idx]['text'] = self::createReportText($report);
            $lessonId = $report['lesson_id'];
            if (!empty($lessonInfo[$lessonId])) {
                $lessonReports[$idx]['lesson_name'] = $lessonInfo[$lessonId]['lesson_name'];
                $lessonReports[$idx]['collection_name'] = $lessonInfo[$lessonId]['collection_name'];

            }
        }

        $result['lessons'] = $lessonReports;

        $result['duration'] = self::formatDuration($result['duration']);

        return $result;
    }

    /**
     * 日报文本
     * @param $report
     * @return array
     */
    public static function createReportText($report)
    {
        $text = [];

        $text[] = sprintf('宝贝共练习了%s', self::formatDuration($report['total_duration']));

        if ($report['part_learn_count'] > 0) {
            $text[] = sprintf('进行了%s个乐句的识谱练习', $report['part_learn_count']);
        }

        if ($report['part_improve_count'] > 0) {
            $text[] = sprintf('进行了%s个乐句的提升练习', $report['part_improve_count']);
        }

        if ($report['test_count'] > 0) {
            $text[] = sprintf('进行了%s次全曲评测，最高%s分', $report['test_count'], $report['test_high_score']);
        }

        return $text;
    }

    public static function createOldReportText($report)
    {
        $text = [];

        $text[] = sprintf('上课模式共%s', self::formatDuration($report['total_duration']));

        if ($report['part_learn_count'] > 0) {
            $text[] = sprintf('进行了%s个乐句的识谱练习', $report['part_learn_count']);
        }

        if ($report['part_improve_count'] > 0) {
            $text[] = sprintf('进行了%s个乐句的提升练习', $report['part_improve_count']);
        }

        if ($report['test_count'] > 0) {
            $text[] = sprintf('进行了%s次全曲评测，最高%s分', $report['test_count'], $report['test_high_score']);
        }

        return $text;
    }

    /**
     * 格式化练琴时间，将秒转为x小时x分
     * @param $seconds
     * @return string
     */
    public static function formatDuration($seconds)
    {
        $hour = intval($seconds / 3600);
        $seconds = $seconds % 3600;

        $minute = ceil($seconds / 60);

        $str = '';
        if($hour > 0) {
            $str .= $hour . '小时';
        }
        if($minute > 0) {
            $str .= $minute . '分钟';
        }

        return $str;
    }
}