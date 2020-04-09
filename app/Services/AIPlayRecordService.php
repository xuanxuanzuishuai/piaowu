<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/3/30
 * Time: 3:32 PM
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\OpernCenter;
use App\Libs\Valid;
use App\Models\AIPlayRecordModel;
use App\Models\PlayRecordModel;
use App\Models\StudentModel;

class AIPlayRecordService
{
    /** app入口 ui_entry */
    const UI_ENTRY_OLD = 1; // 怀旧模式
    const UI_ENTRY_TEST = 2; // 测评
    const UI_ENTRY_LEARN = 3; // 识谱
    const UI_ENTRY_IMPROVE = 4; // 提升
    const UI_ENTRY_CLASS = 5; // 上课模式(5.0以前版本)
    const UI_ENTRY_PRACTICE = 6; // 练习模式(5.0以前版本)

    /** app入口 input_type */
    const INPUT_MIDI = 1; // midi输入
    const INPUT_SOUND = 2; // 声音输入

    /** 演奏模式 practice_mode */
    const PRACTICE_MODE_NORMAL = 1; // 正常
    const PRACTICE_MODE_STEP = 2; // 识谱
    const PRACTICE_MODE_SLOW = 3; // 慢练

    /** 分手 hand */
    const HAND_LEFT = 1; // 左手
    const HAND_RIGHT = 2; // 右手
    const HAND_BOTH = 3; // 双手

    const DEFAULT_APP_VER = '5.0.0';

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
     * @throws RunTimeException
     */
    public static function getDayReportData($studentId, $date)
    {
        $student = StudentModel::getById($studentId);
        if (empty($student)) {
            throw new RunTimeException(['student_not_exist']);
        }

        $startTime = strtotime($date);

        if (empty($startTime)) {
            throw new RunTimeException(['invalid_date']);
        }

        $endTime = $startTime + 86399;

        $records = AIPlayRecordModel::getRecords([
            'student_id' => $studentId,
            'end_time[<>]' => [$startTime, $endTime]
        ]);

        $result = [
            'name' => $student['name'],
            'date' => date("Y年m月d日", $startTime),
            'duration' => 0,
            'lesson_count' => 0,
            'high_score' => 0,
        ];

        $lessonReports = [];
        $topLessonId = null;
        $useOldTextTemp = false;

        foreach ($records as $record) {
            if ($record['duration'] < 1) {
                continue;
            }

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
                    'old_duration' => 0,

                    'class_duration' => 0,
                    'practice_duration' => 0,
                    'part_practice_count' => 0,

                    'sort_score' => 0,
                    'best_record_id' => 0,
                ];
            }

            $result['duration'] += $record['duration']; // 总时长
            $lessonReports[$lessonId]['total_duration'] += $record['duration']; // 单课总时长
            $lessonReports[$lessonId]['sort_score'] += $record['duration']; // 时长作为排序索引

            // 旧版上课模式
            if ($record['ui_entry'] == AIPlayRecordService::UI_ENTRY_CLASS) {
                $lessonReports[$lessonId]['class_duration'] += $record['duration'];
                $useOldTextTemp = true;
            }

            // 旧版练习模式
            if ($record['ui_entry'] == AIPlayRecordService::UI_ENTRY_PRACTICE) {
                $lessonReports[$lessonId]['practice_duration'] += $record['duration'];
                if ($record['is_phrase']) {
                    $lessonReports[$lessonId]['part_practice_count']++;
                }
                $useOldTextTemp = true;
            }

            if ($record['ui_entry'] == AIPlayRecordService::UI_ENTRY_OLD) {
                // 怀旧模式总时长
                $lessonReports[$lessonId]['old_duration'] += $record['duration'];
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
            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, self::DEFAULT_APP_VER);
            $res = $opn->lessonsByIds($lessonIds);
            if (!empty($res) && $res['code'] == Valid::CODE_SUCCESS) {
                $data = $res['data'];
                $lessonInfo = array_combine(array_column($data, 'lesson_id'), $data);
            }
        }

        // 生成文本，补充课程名和书名
        foreach ($lessonReports as $idx => $report) {
            if ($useOldTextTemp) {
                $textArray = self::createOldReportText($report);
            } else {
                $textArray = self::createReportText($report);
            }
            $lessonReports[$idx]['text'] = $textArray;
            $lessonId = $report['lesson_id'];
            if (!empty($lessonInfo[$lessonId])) {
                $lessonReports[$idx]['lesson_name'] = $lessonInfo[$lessonId]['lesson_name'];
                $lessonReports[$idx]['collection_name'] = $lessonInfo[$lessonId]['collection_name'];

            }
        }

        // 课程详情列表
        $result['lessons'] = $lessonReports;

        // 总时长
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

        $text[] = sprintf('宝贝共练习了%s', self::formatDuration($report['total_duration'], true));

        if ($report['part_learn_count'] > 0) {
            $text[] = sprintf('进行了<span>%s</span>个乐句的识谱练习', $report['part_learn_count']);
        }

        if ($report['part_improve_count'] > 0) {
            $text[] = sprintf('进行了<span>%s</span>个乐句的提升练习', $report['part_improve_count']);
        }

        if ($report['test_count'] > 0) {
            $text[] = sprintf('进行了<span>%s</span>次全曲评测，最高<span>%s</span>分', $report['test_count'], $report['test_high_score']);
        }

        return $text;
    }

    /**
     * 旧版本日报文本
     * @param $report
     * @return array
     */
    public static function createOldReportText($report)
    {
        $text = [];

        if ($report['class_duration'] > 0) {
            $text[] = sprintf('上课共%s', self::formatDuration($report['class_duration'], true));
        }

        if ($report['practice_duration'] > 0) {
            $text[] = sprintf('练习共%s', self::formatDuration($report['practice_duration'], true));
        }

        if ($report['part_practice_count'] > 0) {
            $text[] = sprintf('完成<span>%s</span>次分步练习', $report['part_practice_count']);
        }

        if ($report['test_count'] > 0) {
            $text[] = sprintf('进行了<span>%s</span>次全曲评测，最高<span>%s</span>分', $report['test_count'], $report['test_high_score']);
        }

        return $text;
    }

    /**
     * 格式化练琴时间，将秒转为x小时x分
     * @param $seconds
     * @param bool $withFormat
     * @return string
     */
    public static function formatDuration($seconds, $withFormat = false)
    {
        $hour = intval($seconds / 3600);
        $seconds = $seconds % 3600;

        $minute = ceil($seconds / 60);

        $str = '';
        $tagLeft = '';
        $tagEnd = '';

        if ($withFormat) {
            $tagLeft = '<span>';
            $tagEnd = '</span>';
        }

        if($hour > 0) {
            $str .= "{$tagLeft}{$hour}{$tagEnd}小时";
        }
        if($minute > 0) {
            $str .= "{$tagLeft}{$minute}{$tagEnd}分钟";
        }

        return $str;
    }

    /**
     * 格式化分数
     * @param $score
     * @return float
     */
    public static function formatScore($score)
    {
        // 两位小数
        return round($score, 2);
    }

    /**
     * 旧版数据接入
     * 旧版动态练习转为 UI_ENTRY_PRACTICE 类型
     * 旧版测评转为 UI_ENTRY_TEST 类型(与新版测评相同)
     * @param $studentId
     * @param $playData
     * @return int
     */
    public static function insertOldPracticeData($studentId, $playData)
    {
        $now = time();

        if ($playData['ai_type'] == PlayRecordModel::AI_EVALUATE_FRAGMENT) {
            $playData['ai_type'] = PlayRecordModel::AI_EVALUATE_PLAY;
            $playData['if_frag'] = Constants::STATUS_TRUE;
        }

        if (empty($playData['is_frag']) && empty($playData['lesson_sub_id'])) {
            $playData['if_frag'] = Constants::STATUS_FALSE;
        }

        $score = self::formatScore($playData['duration']);

        $recordData = [
            'student_id' => $studentId,
            'lesson_id' => $playData['lesson_id'],
            'score_id' => $playData['opern_id'] ?? 0,
            'record_id' => $playData['ai_record_id'] ?? 0,

            'is_phrase' => $playData['is_frag'] ?? Constants::STATUS_FALSE,
            'phrase_id' => $playData['lesson_sub_id'] ?? 0,
            'practice_mode' => $playData['cfg_mode'] ?? PlayRecordModel::CFG_MODE_NORMAL,
            // 旧版  1双手 2左手 3右手 —> 新版 1左手 2右手 3双手
            'hand' => (((($playData['cfg_hand'] ?? PlayRecordModel::CFG_HAND_BOTH) - 1) + 2) % 3) + 1,
            'ui_entry' => ($playData['lesson_type'] == 1) ? self::UI_ENTRY_TEST : self::UI_ENTRY_PRACTICE,
            'input_type' => $playData['ai_type'] ?? self::INPUT_MIDI,
            'create_time' => $now,
            'end_time' => $now,
            'duration' => $playData['duration'],
            'audio_url' => '',

            'score_final' => $score,
            'score_complete' => $score,
            'score_pitch' => $score,
            'score_rhythm' => $score,
            'score_speed' => $score,
            'score_speed_average' => $score,
        ];

        $recordID = AIPlayRecordModel::insertRecord($recordData);

        return $recordID;
    }

    /**
     * 单课测评成绩单
     * @param $studentId
     * @param $lessonId
     * @param $date
     * @return array
     * @throws RunTimeException
     */
    public static function getLessonTestReportData($studentId, $lessonId, $date)
    {
        $student = StudentModel::getById($studentId);
        if (empty($student)) {
            throw new RunTimeException(['student_not_exist']);
        }

        $startTime = strtotime($date);

        if (empty($startTime)) {
            throw new RunTimeException(['invalid_date']);
        }

        $endTime = $startTime + 86399;

        $records = AIPlayRecordModel::getRecords([
            'student_id' => $studentId,
            'end_time[<>]' => [$startTime, $endTime],
            'lesson_id' => $lessonId,
            'ui_entry' => self::UI_ENTRY_TEST,
            'ORDER' => ['end_time' => 'DESC'],
        ]);

        $result = [
            'lesson_name' => 0,
            'date' => date("Y年m月d日", $startTime),
        ];

        $tests = [];
        $testIdx = -1;
        $highScore = 0;
        $highScoreIdx = -1;

        foreach ($records as $record) {
            $item = [
                'end_time' => date('H:i', $record['end_time']),
                'score' => $record['score_final'] . '分',
                'record_id' => $record['record_id'],
                'tags' => [],
            ];
            $testIdx++;
            $tests[] = $item;

            if ($item['score'] > $highScore) {
                $highScore = $item['score'];
                $highScoreIdx = $testIdx;
            }
        }

        if ($highScoreIdx >= 0) {
            $tests[$highScoreIdx]['tags'][] = '当日最高';
        }

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, self::DEFAULT_APP_VER);
        $res = $opn->lessonsByIds($lessonId);
        if (!empty($res) && $res['code'] == Valid::CODE_SUCCESS) {
            $data = $res['data'];
            $lessonInfo = $data[0];
        }

        $result['tests'] = $tests;

        if (!empty($lessonInfo)) {
            $result['lesson_name'] = $lessonInfo['lesson_name'];
        }

        return $result;
    }
}