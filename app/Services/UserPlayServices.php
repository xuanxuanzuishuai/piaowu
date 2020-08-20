<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/2/21
 * Time: 6:59 PM
 *
 * 用户演奏相关功能
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Valid;
use App\Models\AIPlayRecordModel;
use App\Models\PlayRecordModel;
use App\Models\PlaySaveModel;
use App\Libs\OpernCenter;
use App\Libs\Util;
use App\Libs\MaxHeap;
use App\Models\StudentModel;

class UserPlayServices
{
    /**
     * 匿名登录返回统一记录数据
     * @param $playData
     * @return array
     */
    public static function emptyRecord($playData)
    {
        $playResult = [
            'is_new_high_score' => true,
            'high_score' => 0,
            'current_score' => $playData['score']
        ];
        return [null, ['record_id' => 0, 'play_result' => $playResult]];
    }

        /**
     * 添加一次演奏记录
     * @param $userID
     * @param array $playData 演奏数据
     * @return array [0]errorCode [1]演奏的结果，返回前端
     */
    public static function addRecord($userID, $playData)
    {
        $now = time();

        if ($playData['ai_type'] == PlayRecordModel::AI_EVALUATE_FRAGMENT) {
            $playData['ai_type'] = PlayRecordModel::AI_EVALUATE_PLAY;
            $playData['if_frag'] = Constants::STATUS_TRUE;
        }

        // 兼容旧逻辑,没有lesson_sub_id的为全曲演奏
        if (empty($playData['is_frag']) && empty($playData['lesson_sub_id'])) {
            $playData['if_frag'] = Constants::STATUS_FALSE;
        }

        $recordData = [
            'student_id' => $userID,
            'category_id' => $playData['category_id'],
            'collection_id' => $playData['collection_id'],
            'lesson_id' => $playData['lesson_id'],
            'schedule_id' => $playData['schedule_id'],
            'lesson_type' => $playData['lesson_type'],
            'client_type' => $playData['client_type'],
            'lesson_sub_id' => $playData['lesson_sub_id'],
            'ai_record_id' => $playData['ai_record_id'],
            'created_time' => $now,
            'duration' => $playData['duration'],
            'score' => $playData['score'],
            'midi' => $playData['midi'],
            'ai_type' => $playData['ai_type'] ?? PlayRecordModel::TYPE_DYNAMIC,
            'data' => json_encode($playData),

            'opern_id' => $playData['opern_id'] ?? 0,
            'is_frag' => $playData['is_frag'] ?? 0,
            'frag_key' => $playData['frag_key'] ?? '-',
            'cfg_hand' => $playData['cfg_hand'] ?? PlayRecordModel::CFG_HAND_BOTH,
            'cfg_mode' => $playData['cfg_mode'] ?? PlayRecordModel::CFG_MODE_NORMAL,
        ];

        $recordID =  PlayRecordModel::insertRecord($recordData);

        list($saveID, $playResult) = self::updateSave($userID, $playData);

        if (empty($saveID)) {
            return ['play_save_failure'];
        }

        StudentModel::updateRecord($userID, ['last_play_time' => $now], false);

        return [null, ['record_id' => $recordID, 'play_result' => $playResult]];
    }

    /**
     * 更新演奏记录的数据
     * 用于异步更新midi文件url等数据
     *
     * @param $userID
     * @param int $recordID 演奏记录的ID
     * @param array $update 更新的数据 ['key' => 'value', ...]
     * @return null|string errorCode
     */
    public static function updateRecord($userID, $recordID, $update)
    {
        if (empty($userID)) {
            return 'invalid_user_id';
        }

        if (empty($recordID)) {
            return 'invalid_record_id';
        }

        $record = PlayRecordModel::getById($recordID);

        if (empty($record) || $record['user_id'] != $userID) {
            return 'invalid_record_id';
        }

        $count = PlayRecordModel::updateRecord($recordID, $update);
        if ($count < 1) {
            return 'update_record_failure';
        }

        return null;
    }

    /**
     * 获取演奏记录存档
     * @param int $userID
     * @param int $lessonID
     * @return mixed
     */
    public static function getSave($userID, $lessonID)
    {
        $save = PlaySaveModel::getByLesson($userID, $lessonID);
        return $save;
    }

    /**
     * 更新用户曲目存档
     *
     * @param $userID
     * @param array $playData 演奏数据
     * @return array [0]存档ID [1]演奏的结果，返回前端
     */
    public static function updateSave($userID, $playData)
    {
        $lessonID = $playData['lesson_id'];
        if($playData['lesson_type'] == PlayRecordModel::TYPE_AI){
            $save = PlaySaveModel::getByLesson($userID, $lessonID, PlayRecordModel::TYPE_AI);
            if (empty($save)) {
                list($newSave, $playResult) = self::createNewAISave($userID, $playData);
                $saveID = PlaySaveModel::insertRecord($newSave);
            } else {
                list($saveUpdate, $playResult) = self::getSaveAIUpdate($save, $playData);
                $saveID = PlaySaveModel::updateRecord($save['id'], $saveUpdate);
            }
        }else{
            $save = PlaySaveModel::getByLesson($userID, $lessonID, PlayRecordModel::TYPE_DYNAMIC);
            if (empty($save)) {
                list($newSave, $playResult) = self::createNewSave($userID, $playData);
                $saveID = PlaySaveModel::insertRecord($newSave);
            } else {
                list($saveUpdate, $playResult) = self::getSaveUpdate($save, $playData);
                $saveID = PlaySaveModel::updateRecord($save['id'], $saveUpdate);
            }
        }

        return [$saveID, $playResult];
    }

    /**
     * 创建一个新存档
     * 存档以(userID, opernID)为唯一标识
     *
     * @param $userID
     * @param array $playData 演奏数据
     * @return array [0]存档数据 [1]演奏结果
     */
    public static function createNewSave($userID, $playData)
    {
        $playResult = [
            'is_new_high_score' => true,
            'high_score' => 0,
            'current_score' => $playData['score']
        ];

        $save = [
            'student_id' => $userID,
            'lesson_id' => $playData['lesson_id'],
            'last_play_time' => time(),
            'total_duration' => $playData['duration'],
            'save_type' => PlayRecordModel::TYPE_DYNAMIC
        ];

        // 创建json数据，保存步骤的演奏完成情况
        $jsonData = [
            'lesson_sub_complete' => []
        ];

        if (empty($playData['lesson_sub_id'])) {
            // 未传子步骤id表示是全曲分数
            $save['high_score'] = $playData['score'];
            $playResult['high_score'] = $playData['score'];
        } else {
            // 子步骤的最高分保存在json里
            $stepID = $playData['lesson_sub_id'];
            $jsonData['lesson_sub_complete'][$stepID] = [
                'complete' => true,
                'high_score' => $playData['score']
            ];
        }

        $save['data'] = json_encode($jsonData);

        return [$save, $playResult];
    }


    /**
     * 创建一个AI练琴新存档
     * 存档以(userID, opernID)为唯一标识
     *
     * @param $userID
     * @param array $playData 演奏数据
     * @return array [0]存档数据 [1]演奏结果
     */
    public static function createNewAISave($userID, $playData)
    {
        $playResult = [
            'is_new_high_score' => true,
            'ai_high_score' => 0,
            'ai_current_score' => $playData['score']
        ];

        $save = [
            'student_id' => $userID,
            'lesson_id' => $playData['lesson_id'],
            'last_play_time' => time(),
            'total_duration' => $playData['duration'],
            'save_type' => PlayRecordModel::TYPE_AI,
            'score_detail' => json_decode($playData['score_detail']),
        ];

        // 创建json数据，保存步骤的演奏完成情况
        $jsonData = [
            'lesson_sub_complete' => []
        ];

        $save['high_score'] = $playData['score'];
        $playResult['high_score'] = $playData['score'];
        $save['data'] = json_encode($jsonData);

        return [$save, $playResult];
    }

    /**
 * 更新一个存档
 * 存档以(userID, opernID)为唯一标识
 *
 * @param array $save 旧存档
 * @param array $playData 演奏数据
 * @return array [0]存档更新数据 [1]演奏结果
 */
    public static function getSaveUpdate($save, $playData)
    {
        $playResult = [
            'is_new_high_score' => false,
            'current_score' => $playData['score']
        ];

        $saveUpdate = [
            'last_play_time' => time(),
            'total_duration[+]' => $playData['duration'],
        ];

        if (empty($playData['lesson_sub_id'])) {
            // 全曲检查最高分
            if ($playData['score'] > $save['high_score']) {
                $saveUpdate['high_score'] = $playData['score'];

                $playResult['high_score'] = $playData['score'];
                $playResult['is_new_high_score'] = true;
            } else {
                $playResult['high_score'] = $save['high_score'];
            }

        } else {
            // 步骤检查最高分
            $stepID = $playData['lesson_sub_id'];
            $jsonData = json_decode($save['data'], true);
            $stepData = $jsonData['lesson_sub_complete'][$stepID];

            if (empty($stepData)) { // 没有步骤信息时创建一份
                $playResult['high_score'] = 0;
                $playResult['is_new_high_score'] = true;

                $jsonData['lesson_sub_complete'][$stepID] = [
                    'complete' => true,
                    'high_score' => $playData['score']
                ];

                $saveUpdate['data'] = json_encode($jsonData);

            } elseif ($playData['score'] > $stepData['high_score']) { // 有步骤信息，打破步骤最高分记录
                $playResult['high_score'] = $stepData['high_score'];
                $playResult['is_new_high_score'] = true;

                $jsonData['lesson_sub_complete'][$stepID]['high_score'] = $playData['score'];

                $saveUpdate['data'] = json_encode($jsonData);
            }
        }

        return [$saveUpdate, $playResult];
    }

    /**
     * 更新一个AI存档
     * 存档以(userID, opernID)为唯一标识
     *
     * @param array $save 旧存档
     * @param array $playData 演奏数据
     * @return array [0]存档更新数据 [1]演奏结果
     */
    public static function getSaveAIUpdate($save, $playData)
    {
        $playResult = [
            'is_new_high_score' => false,
            'current_score' => $playData['score']
        ];

        $saveUpdate = [
            'last_play_time' => time(),
            'total_duration[+]' => $playData['duration'],
        ];

        if ($playData['score'] > $save['high_score']) {
            $saveUpdate['high_score'] = $playData['score'];

            $playResult['high_score'] = $playData['score'];
            $playResult['is_new_high_score'] = true;
        } else {
            $playResult['high_score'] = $save['high_score'];
        }
        return [$saveUpdate, $playResult];
    }

    /**
     * 新版7天数据统计
     * @param $studentId
     * @param int $day
     * @param null $endTime
     * @return array
     */
    public static function pandaPlayRecordBrief($studentId, $day = 7, $endTime = null)
    {
        list($start, $end) = Util::nDaysBeforeNow($endTime, $day);

        $records = AIPlayRecordModel::getRecords(['student_id' => $studentId, 'end_time[<>]' => [$start, $end]]);

        $days = [];
        $lessons = [];
        foreach ($records as $play) {
            $day = date("Y-m-d", $play['end_time']);

            if(!isset($days[$day])){
                $days[$day] = 1;
            }

            if(!isset($lessons[$play['lesson_id']])){
                $lessons[$play['lesson_id']] = 1;
            }
        }
        return [
            'lesson_count' => count($lessons),
            'days' => count($days)
        ];
    }

    /**
     * 新版7天练琴曲谱统计
     * @param $studentId
     * @param $appVersion
     * @param int $day
     * @param null $endTime
     * @return array
     */
    public static function pandaPlayRecord($studentId, $appVersion, $day = 7, $endTime = null)
    {
        list($start, $end) = Util::nDaysBeforeNow($endTime, $day);

        $records = AIPlayRecordModel::getRecords([
            'student_id' => $studentId,
            'end_time[<>]' => [$start, $end]
        ]);

        $days = [];
        $lessonReports = [];
        foreach ($records as $record) {
            // 时长 < 1 或 5.0之前的老版本
            if ($record['duration'] < 1 || $record['old_format']) {
                continue;
            }

            $day = date("Y-m-d", $record['end_time']);
            if(!isset($days[$day])){
                $days[$day] = 1;
            }

            $lessonId = $record['lesson_id'];

            if (empty($lessonReports[$lessonId])) {
                $lessonReports[$lessonId] = [
                    'lesson_id' => $lessonId,
                    'lesson_name' => '',
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
                    'end_time' => $record['end_time'],
                    'plays' => new MaxHeap('score_final')
                ];
            }

            $lessonReports[$lessonId]['plays']->insert($record);


            // 以lesson为单位，查出该lesson的最近练琴时间
            if($record['end_time'] > $lessonReports[$lessonId]['end_time']){
                $details[$lessonId]['end_time'] = $record['end_time'];
            }

            $lessonReports[$lessonId]['total_duration'] += $record['duration']; // 单课总时长

            $isNormalData = $record['data_type'] == AIPlayRecordModel::DATA_TYPE_NORMAL ? 1 : 0;

            // case 怀旧模式
            if ($record['ui_entry'] == AIPlayRecordModel::UI_ENTRY_OLD) {
                $lessonReports[$lessonId]['old_duration'] += $record['duration'];
                continue;
            }

            // case 识谱
            if ($record['ui_entry'] == AIPlayRecordModel::UI_ENTRY_LEARN && $isNormalData) {
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
            if ($record['ui_entry'] == AIPlayRecordModel::UI_ENTRY_IMPROVE && $isNormalData) {
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
                    if ($isNormalData) {
                        $countKey = 'part_test_count';
                        $lessonReports[$lessonId][$countKey]++;
                    }

                    $lessonReports[$lessonId]['part_test_duration'] += $record['duration'];
                    continue;
                }

                if ($isNormalData) {
                    $lessonReports[$lessonId]['test_count']++;
                }

                // 单课最高分
                $curMaxScore = $lessonReports[$lessonId]['test_high_score'];
                if ($curMaxScore < $record['score_final']) {
                    $lessonReports[$lessonId]['test_high_score'] = $record['score_final'];
                }
                continue;
            }
        }

        // 按照曲目最后的练习时间，倒序展示曲目
        usort($lessonReports, function ($a, $b) {
            return $a['end_time'] < $b['end_time'];
        });

        // 获取lesson的信息
        $lessonInfo = [];
        $lessonIds = array_column($lessonReports, 'lesson_id');
        if (!empty($lessonIds)) {
            $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, $appVersion);
            $res = $opn->lessonsByIds($lessonIds);
            if (!empty($res) && $res['code'] == Valid::CODE_SUCCESS) {
                $data = $res['data'];
                $lessonInfo = array_combine(array_column($data, 'lesson_id'), $data);
            }
        }

        $lessonRecords = [];
        foreach ($lessonReports as $idx => $report) {
            // 取3个最高分的爱练琴记录，按照时间排序
            $report['plays'] = MaxHeap::nLargest($report['plays']);
            usort($report['plays'], function ($a, $b) {
                return $a['end_time'] < $b['end_time'];
            });

            $lessonRecords[] = [
                'total_duration' => $report['total_duration'],
                'lesson_id' => $report['lesson_id'],
                'lesson_name' => !empty($lessonInfo[$lessonId]) ? $lessonInfo[$lessonId]['lesson_name'] : '', // 曲谱名
                'text' => self::createReportText($report), // 生成文本
                'plays' => $report['plays'], // 得分最高的三次的测评
            ];
        }

        return [
            'lesson_count' => count($lessonRecords), // 总练习曲目
            'days' => count($days), // 总练习天数
            'token' => AIBackendService::genStudentToken($studentId),
            'lessons' => $lessonRecords
        ];
    }

    /**
     * 日报文本
     * @param $report
     * @return array
     */
    public static function createReportText($report)
    {
        $text[] = sprintf('最近7天练琴时长：%s', self::formatDuration($report['total_duration'], true));

        if ($report['learn_count'] > 0) {
            $text[] = '进行了全曲识谱练习';
        } elseif ($report['part_learn_count'] > 0) {
            $text[] = sprintf('进行了%s个乐句的识谱练习', self::formatStyle($report['part_learn_count']));
        }

        if ($report['improve_count'] > 0) {
            $text[] = '进行了全曲提升练习';
        } elseif ($report['part_improve_count'] > 0) {
            $text[] = sprintf('进行了%s个乐句的提升练习', self::formatStyle($report['part_improve_count']));
        }

        if ($report['test_count'] > 0) {
            $text[] = sprintf(
                '进行了%s次全曲评测，最高%s分',
                self::formatStyle($report['test_count']),
                self::formatStyle(AIPlayRecordService::formatScore($report['test_high_score']))
            );
        }

        if ($report['old_duration'] > 0) {
            $text[] = sprintf('进行了%s怀旧模式练习', self::formatDuration($report['old_duration']));
        }

        if ($report['part_test_duration'] > 0) {
            $text[] = sprintf('进行了%s分手分段评测', self::formatDuration($report['part_test_duration']));
        }

        return $text;
    }

    public static function formatStyle($key)
    {
        return "<b>{$key}</b>";
    }

    /**
     * 格式化：*时*分*秒
     * @param $seconds
     * @param bool $needHour
     * @return string
     */
    public static function formatDuration($seconds, $needHour = false)
    {
        if ($needHour && $seconds > 3600) {
            $hour = intval($seconds / 3600);
            $minute = intval($seconds % 3600 / 60);
        } else {
            $hour = 0;
            $minute = intval($seconds / 60);
        }
        $seconds = $seconds % 60;

        $str = '';
        if ($hour > 0) {
            $str .= self::formatStyle($hour) . "时";
        }
        if ($minute > 0) {
            $str .= self::formatStyle($minute) . "分";
        }
        if ($seconds > 0) {
            $str .= self::formatStyle($seconds) . "秒";
        }

        return $str;
    }
}