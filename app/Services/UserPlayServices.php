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
use App\Libs\SimpleLogger;
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
            'ai_type' => $playData['ai_type'],
            'data' => json_encode($playData),

            'opern_id' => $playData['opern_id'] ?? 0,
            'is_frag' => $playData['is_frag'] ?? 0,
            'frag_key' => $playData['frag_key'] ?? '-',
            'cfg_hand' => $playData['cfg_hand'] ?? 1,
            'cfg_mode' => $playData['cfg_mode'] ?? 1,
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

    public static function pandaPlayBrief($studentId, $days=7, $endTime=null)
    {
        // 取最近7天的演奏记录
        list($start, $end) = Util::nDaysBeforeNow($endTime, $days);
        $where = [
            'created_time[>]' => $start,
            'created_time[<]' => $end,
            'student_id' => (int)$studentId,
            'client_type' => [PlayRecordModel::CLIENT_STUDENT, PlayRecordModel::CLIENT_PANDA_MINI]
        ];
        $plays = PlayRecordModel::getRecords($where);
        $daysFilter = [];
        $lessonFilter = [];
        foreach ($plays as $play) {
            $day = date("Y-m-d", $play['created_time']);
            if(!isset($daysFilter[$day])){
                $daysFilter[$day] = 1;
            }

            if(!isset($lessonFilter[$play['lesson_id']])){
                $lessonFilter[$play['lesson_id']] = 1;
            }
        }
        return ['lesson_count' =>count($lessonFilter), 'days' => count($daysFilter)];
    }

    public static function pandaPlayDetail($studentId, $appVersion, $days=7, $endTime=null)
    {
        // 取最近7天的演奏记录
        list($start, $end) = Util::nDaysBeforeNow($endTime, $days);
        $where = [
            'created_time[>]' => $start,
            'created_time[<]' => $end,
            'student_id' => (int)$studentId,
            'client_type' => [PlayRecordModel::CLIENT_STUDENT, PlayRecordModel::CLIENT_PANDA_MINI]
        ];
        $plays = PlayRecordModel::getRecords($where);

        // 统计练琴演奏记录
        $details = [];
        $daysFilter = [];
        foreach ($plays as $play) {
            // 按天聚合,计算练琴天数
            $day = date("Y-m-d", $play['created_time']);
            if(!isset($daysFilter[$day])){
                $daysFilter[$day] = 1;
            }

            $lessonId = $play['lesson_id'];
            if (!isset($details[$lessonId])){
                $details[$lessonId] = [
                    'practice_time' => 0,
                    'step_times' => 0,
                    'whole_times' => 0,
                    'whole_best' => 0,
                    'ai_times' => 0,
                    'ai_best' => 0,
                    'created_time' => 0,
                    'plays' => new MaxHeap('score'),
                    'ai_fragment_times' => 0 // ai测评分手分段练习次数
                ];
            }
            // 以lesson为单位，查出该lesson的最近练琴时间
            if($play['created_time'] > $details[$lessonId]['created_time']){
                $details[$lessonId]['created_time'] = $play['created_time'];
            }
            // 分类计算指标
            $details[$lessonId]['practice_time'] += $play['duration'];
            $score = Util::floatIsInt($play['score']) ? (int)$play['score'] : (float)$play['score'];
            if ($play['lesson_type'] == PlayRecordModel::TYPE_AI){
                // ai测评与语音识别归为一类，ai的分手分段单独归为一类
                if( $play['ai_type'] == PlayRecordModel::AI_EVALUATE_PLAY or
                    $play['ai_type'] == PlayRecordModel::AI_EVALUATE_AUDIO){
                    $details[$lessonId]['ai_times'] += 1;
                    if ($score > $details[$lessonId]['ai_best']){
                        $details[$lessonId]['ai_best'] = $score;
                    }
                }elseif ($play['ai_type'] == PlayRecordModel::AI_EVALUATE_FRAGMENT){
                    $details[$lessonId]['ai_fragment_times'] += 1;
                }
            } else {
                if(!empty($play['lesson_sub_id'])){
                    $details[$lessonId]['step_times'] += 1;
                }else{
                    $details[$lessonId]['whole_times'] += 1;
                    if ($score > $details[$lessonId]['whole_best']){
                        $details[$lessonId]['whole_best'] = $score;
                    }
                }
            }
            // ai练琴记录的3个最高分，用于五维图展示
            if($play['lesson_type'] == PlayRecordModel::TYPE_AI and (
                    $play['ai_type'] == PlayRecordModel::AI_EVALUATE_PLAY or
                    $play['ai_type'] == PlayRecordModel::AI_EVALUATE_AUDIO)){
                $temp = [];
                $temp['id'] = $play['id'];
                $temp['score'] = $play['score'];
                $temp['ai_record_id'] = $play['ai_record_id'];
                $temp['play_midi'] = $play['ai_type'] == PlayRecordModel::AI_EVALUATE_PLAY ? 1 : 0;
                $temp['score_detail'] = json_decode($play['data'], true);
                $temp['created_time'] = $play['created_time'];
                $details[$lessonId]['plays']->insert($temp);
            }
        }

        // 插入课程名称信息
        $ret = [];
        $lessonIds = array_keys($details);
        $lessons = OpernService::getLessonForJoin($lessonIds,
            OpernCenter::PRO_ID_AI_STUDENT, $appVersion, 0, 1);
        foreach ($details as $lessonId => $detail){
            $lesson = $lessons[$lessonId];
            $detail['lesson_id'] = $lessonId;
            $detail['lesson_name'] = !empty($lesson) ? $lesson['lesson_name'] : '';
            // 取3个最高分的爱练琴记录，按照时间排序
            $detail['plays'] = MaxHeap::nLargest($detail['plays']);
            $dates = array_column($detail['plays'],'created_time');
            array_multisort($dates,SORT_DESC, $detail['plays']);
            array_push($ret, $detail);
        }

        // 将统计完数据整体按照最后练习时间排序
        $_dates = array_column($ret,'created_time');
        array_multisort($_dates,SORT_DESC, $ret);

        return ['lessons' => $ret,
                'lesson_count' =>count($details),
                'days' => count($daysFilter),
                'token' => AIBackendService::genStudentToken($studentId)
        ];
    }
}