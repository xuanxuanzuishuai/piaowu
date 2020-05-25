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
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\AIPlayRecordModel;
use App\Models\AppVersionModel;
use App\Models\PlayRecordModel;
use App\Models\StudentModel;
use Medoo\Medoo;

class AIPlayRecordService
{
    const DEFAULT_APP_VER = '5.0.0';

    /**
     * 演奏数据
     * @param $studentId
     * @param $params
     * @return int
     */
    public static function end($studentId, $params)
    {
        $now = time();

        // 处理毫秒时间戳，转为秒
        if ($params['created_at'] > 2000000000) {
            $params['created_at'] = intval($params['created_at']/1000);
        }

        $recordData = [
            'student_id' => $studentId,
            'lesson_id' => $params['lesson_id'] ?? 0,
            'score_id' => $params['score_id'] ?? 0,
            'record_id' => $params['record_id'] ?? 0,
            'is_phrase' => $params['is_phrase'] ?? 0,
            'phrase_id' => $params['phrase_id'] ?? 0,
            'practice_mode' => $params['practice_mode'] ?? 0,
            'hand' => $params['hand'] ?? 0,
            'ui_entry' => $params['ui_entry'] ?? 0,
            'input_type' => $params['input_type'] ?? 0,
            'create_time' => $now,

            // 演奏结束时间，演奏时间跨天时，数据归为结束时间所在天
            'end_time' => $params['created_at'] + $params['duration'],
            'duration' => $params['duration'],
            'audio_url' => $params['audio_url'] ?? '',
            'score_final' => self::formatScore($params['score_final']),
            'score_complete' => self::formatScore($params['score_complete']),
            'score_pitch' => self::formatScore($params['score_pitch']),
            'score_rhythm' => self::formatScore($params['score_rhythm']),
            'score_speed' => self::formatScore($params['score_speed']),
            'score_speed_average' => self::formatScore($params['score_speed_average']),
        ];

        $recordId =  AIPlayRecordModel::insertRecord($recordData);

        if (empty($recordId)) {
            return 0;
        }

        StudentModel::updateRecord($studentId, ['last_play_time' => $now], false);

        return $recordId;
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
                    'total_duration' => 0, // 总时长
                    'learn_count' => 0, // 识谱全曲次数
                    'part_learn_count' => 0, // 识谱乐句数(相同乐句记1次)
                    'part_learn_id_map' => [], // 识谱乐句id集合
                    'improve_count' => 0, // 提高全曲次数
                    'part_improve_count' => 0, // 提高乐句数(相同乐句记1次)
                    'part_improve_id_map' => [], // 提高乐句id集合
                    'test_count' => 0, // 全曲测评
                    'part_test_count' => 0, // 非全曲测评
                    'test_high_score' => 0, // 全曲测评最高分
                    'old_duration' => 0, // 怀旧模式时长

                    'class_duration' => 0, // 上课模式时长(旧版)
                    'practice_duration' => 0, // 分步练习模式时长(旧版)
                    'part_practice_count' => 0, // 分步练习次数(旧版)

                    'sort_score' => 0, // 排序优先级
                    'best_record_id' => 0,
                ];
            }

            $result['duration'] += $record['duration']; // 总时长
            $lessonReports[$lessonId]['total_duration'] += $record['duration']; // 单课总时长
            $lessonReports[$lessonId]['sort_score'] += $record['duration']; // 时长作为排序索引

            if ($record['old_format']) {
                $useOldTextTemp = true;
            }

            // switch ($record['ui_entry'])

            // case 上课模式(旧版)
            if ($record['ui_entry'] == AIPlayRecordModel::UI_ENTRY_CLASS) {
                $lessonReports[$lessonId]['class_duration'] += $record['duration'];
                continue;
            }

            // case 练习模式(旧版)
            if ($record['ui_entry'] == AIPlayRecordModel::UI_ENTRY_PRACTICE) {
                $lessonReports[$lessonId]['practice_duration'] += $record['duration'];
                // 旧版趣味练习，非双手全曲的测评，都算作分步练习
                $lessonReports[$lessonId]['part_practice_count']++;
                continue;
            }

            // case 怀旧模式
            if ($record['ui_entry'] == AIPlayRecordModel::UI_ENTRY_OLD) {
                $lessonReports[$lessonId]['old_duration'] += $record['duration'];
                continue;
            }

            // case 识谱
            if ($record['ui_entry'] == AIPlayRecordModel::UI_ENTRY_LEARN) {
                if ($record['is_phrase']) {
                    // 统计乐句数量，每个乐句只算一次
                    if (empty($lessonReports[$lessonId]['part_learn_id_map'][$record['phrase_id']])) {
                        $lessonReports[$lessonId]['part_learn_id_map'][$record['phrase_id']] = true;
                        $lessonReports[$lessonId]['part_learn_count']++;
                    }
                } else {
                    // 统计全曲数量
                    $lessonReports[$lessonId]['learn_count']++;
                }
                continue;
            }

            // case 提高
            if ($record['ui_entry'] == AIPlayRecordModel::UI_ENTRY_IMPROVE) {
                if ($record['is_phrase']) {
                    // 统计乐句数量，每个乐句只算一次
                    if (empty($lessonReports[$lessonId]['part_improve_id_map'][$record['phrase_id']])) {
                        $lessonReports[$lessonId]['part_improve_id_map'][$record['phrase_id']] = true;
                        $lessonReports[$lessonId]['part_improve_count']++;
                    }
                } else {
                    // 统计全曲数量
                    $lessonReports[$lessonId]['improve_count']++;
                }
                continue;
            }

            // case 测评 包含旧版测评
            if ($record['ui_entry'] == AIPlayRecordModel::UI_ENTRY_TEST) {
                // 分手分段测评不计入全曲测评次数
                if ($record['is_phrase'] || ($record['hand'] != AIPlayRecordModel::HAND_BOTH)) {
                    $countKey = $useOldTextTemp ? 'part_practice_count' : 'part_test_count';
                    $lessonReports[$lessonId][$countKey]++;
                    continue;
                }

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

        if ($report['learn_count'] > 0) {
            $text[] = '进行了全曲识谱练习';
        } elseif ($report['part_learn_count'] > 0) {
            $text[] = sprintf('进行了<span>%s</span>个乐句的识谱练习', $report['part_learn_count']);
        }

        if ($report['improve_count'] > 0) {
            $text[] = '进行了全曲提升练习';
        } elseif ($report['part_improve_count'] > 0) {
            $text[] = sprintf('进行了<span>%s</span>个乐句的提升练习', $report['part_improve_count']);
        }

        if ($report['test_count'] > 0) {
            $text[] = sprintf(
                '进行了<span>%s</span>次全曲评测，最高<span>%s</span>分',
                $report['test_count'],
                self::formatScore($report['test_high_score'])
            );
        }

        if ($report['old_duration'] > 0) {
            $text[] = sprintf('进行了%s怀旧模式练习', self::formatDuration($report['old_duration'], true));
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
            $text[] = sprintf('分步练习共%s', self::formatDuration($report['practice_duration'], true));
        }

        if ($report['part_practice_count'] > 0) {
            $text[] = sprintf('完成<span>%s</span>次分步练习', $report['part_practice_count']);
        }

        if ($report['test_count'] > 0) {
            $text[] = sprintf(
                '进行了<span>%s</span>次全曲评测，最高<span>%s</span>分',
                $report['test_count'],
                self::formatScore($report['test_high_score'])
            );
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
        $minute = intval($seconds / 60);
        $seconds = $seconds % 60;

        $str = '';
        $tagLeft = '';
        $tagEnd = '';

        if ($withFormat) {
            $tagLeft = '<span>';
            $tagEnd = '</span>';
        }

        if($minute > 0) {
            $str .= "{$tagLeft}{$minute}{$tagEnd}分";
        }
        if($seconds > 0) {
            $str .= "{$tagLeft}{$seconds}{$tagEnd}秒";
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
        if (empty($score) || !is_numeric($score)) {
            return 0;
        }
        // 两位小数
        return round($score, 1);
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
            $playData['is_frag'] = Constants::STATUS_TRUE;
        }

        if (empty($playData['is_frag']) && empty($playData['lesson_sub_id'])) {
            $playData['is_frag'] = Constants::STATUS_FALSE;
        }

        $score = self::formatScore($playData['score']);

        if ($playData['lesson_type'] == PlayRecordModel::TYPE_AI
            && $playData['is_frag'] == Constants::STATUS_FALSE
            && $playData['cfg_hand'] == PlayRecordModel::CFG_HAND_BOTH) {
            $uiEntry = AIPlayRecordModel::UI_ENTRY_TEST;
        } else {
            // 新版怀旧模式也用旧版的接口按 old_format 来区分是新版app怀旧模式还是老版app数据
            $uiEntry = ($playData['old_format'] === Constants::STATUS_FALSE)
                ? AIPlayRecordModel::UI_ENTRY_OLD : AIPlayRecordModel::UI_ENTRY_PRACTICE;
        }

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
            'ui_entry' => $uiEntry,
            'input_type' => $playData['ai_type'] ?? AIPlayRecordModel::INPUT_MIDI,
            'old_format' => $playData['old_format'] ?? Constants::STATUS_TRUE,

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
     * 旧版上课数据接入
     * 旧版上课模式转为 UI_ENTRY_CLASS 类型
     * @param $studentId
     * @param $playData
     * @return int
     */
    public static function insertOldClassData($studentId, $playData)
    {
        $endTime = $playData['start_time'] + $playData['duration'];
        $score = 0;

        $recordData = [
            'student_id' => $studentId,
            'lesson_id' => $playData['lesson_id'],
            'score_id' => 0,
            'record_id' => $playData['best_record_id'] ?? 0,

            'is_phrase' => Constants::STATUS_FALSE,
            'phrase_id' => 0,
            'practice_mode' => AIPlayRecordModel::PRACTICE_MODE_NORMAL,
            'hand' => AIPlayRecordModel::HAND_BOTH,
            'ui_entry' => AIPlayRecordModel::UI_ENTRY_CLASS,
            'input_type' => AIPlayRecordModel::INPUT_MIDI,
            'old_format' => Constants::STATUS_TRUE,

            'create_time' => $endTime,
            'end_time' => $endTime,
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
            'ui_entry' => AIPlayRecordModel::UI_ENTRY_TEST,
            'is_phrase' => Constants::STATUS_FALSE,
            'hand' => AIPlayRecordModel::HAND_BOTH,
            'ORDER' => ['end_time' => 'DESC'],
        ]);

        $result = [
            'lesson_id' => $lessonId,
            'lesson_name' => '',
            'date' => date("Y年m月d日", $startTime),
        ];

        $tests = [];
        $testIdx = -1;
        $highScore = 0;
        $highScoreIdx = -1;

        foreach ($records as $record) {
            $item = [
                'end_time' => date('H:i', $record['end_time']),
                'score' => self::formatScore($record['score_final']) . '分',
                'record_id' => $record['record_id'],
                'tags' => [],
            ];
            $testIdx++;
            $tests[] = $item;

            if ($record['score_final'] > $highScore) {
                $highScore = $record['score_final'];
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

    /**
     * 获取曲目排名
     * @param $lessonId
     * @param $studentId
     * @return array
     */
    public static function getLessonRankData($lessonId, $studentId)
    {
        $ret = [];
        $myself = null;

        $ranks = AIPlayRecordModel::getLessonPlayRank($lessonId);

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
            $v['score'] = self::formatScore($v['score']);
            array_push($ret, $v);
            if($v['student_id'] == $studentId){
                $myself = $v;
            }
        }

        if(empty($myself)) {
            $student = StudentModel::getById($studentId);

            $bestRecord = AIPlayRecordModel::getStudentLessonBestRecord($studentId, $lessonId);
            $order = empty($bestRecord) ? -1 : 0;

            // order 0 表示未上榜 -1 表示未演奏
            $myself = [
                'name' => $student['name'],
                'score' => $bestRecord['score'] ?? 0,
                'order' => $order,
            ];
            $myself['score'] = self::formatScore($myself['score']);
        }
        return ['ranks' => $ret, 'myself' => $myself, 'hasOrg' => false];
    }

    /**
     * 个人练琴
     * @param $studentId
     * @return array
     */
    public static function getStudentTotalSumData($studentId)
    {
        $sum = AIPlayRecordModel::getStudentTotalSum($studentId);

        if (empty($sum)) {
            return ['lesson_count' => 0, 'sum_duration' => 0];
        }

        $sum['sum_duration'] = $sum['sum_duration'] ?? 0;

        return $sum;
    }

    /**
     * 获取学生今日练琴总时长
     * @param $studentId
     * @return int
     */
    public static function getStudentSumDuration($studentId)
    {
        $startTime = strtotime('today');
        $duration = AIPlayRecordModel::getRecord([
            'student_id' => $studentId,
            'end_time[>=]' => $startTime,
            'end_time[<]' => $startTime + 86400
        ], [
            'sum_duration' => Medoo::raw('SUM(duration)'),
        ]);
        return $duration['sum_duration'] ?? 0;
    }

    /**
     * 是否时旧版本app
     * @param $version
     * @return bool
     */
    public static function isOldVersionApp($version)
    {
        // 默认是新版
        if (empty($version)) {
            return false;
        }

        // verCmp $a < $b 时返回1
        $cmpRet = AppVersionModel::verCmp($version, self::DEFAULT_APP_VER);
        return $cmpRet > 0;
    }


    /**
     * 学生练琴数据统计
     * @param $params
     * @param $employeeId
     * @param $roleId
     * @return array
     */
    public static function studentPlayStatistics($params, $employeeId, $roleId)
    {
        if (EmployeeService::isAssistantRole($roleId)) {
            $params['assistant_id'] = $employeeId;
        }
        if (EmployeeService::isCourseManagerRole($roleId)) {
            $params['course_manage_id'] = $employeeId;
        }

        list($records, $totalCount) = AIPlayRecordModel::recordStatistics($params);

        $reviewStatus = DictService::getTypeMap(Constants::DICT_TYPE_REVIEW_COURSE_STATUS);

        foreach ($records as &$record) {
            $record['mobile'] = Util::hideUserMobile($record['mobile']);
            $record['review_course_status'] = $reviewStatus[$record['has_review_course']];
            $record['teaching_start_time'] = !empty($record['teaching_start_time']) ? date('Y-m-d', $record['teaching_start_time']) : '';
            $record['total_duration'] = ceil($record['total_duration'] / 60);
            $record['avg_duration'] = ceil($record['avg_duration'] / 60);
        }
        return [$records, $totalCount];
    }
}