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

use App\Models\PlayRecordModel;
use App\Models\PlaySaveModel;

class UserPlayServices
{
    /**
     * 添加一次演奏记录
     * @param $userID
     * @param array $playData 演奏数据
     * @return array [0]errorCode [1]演奏的结果，返回前端
     */
    public static function addRecord($userID, $playData)
    {
        $recordData = [
            'student_id' => $userID,
            'category_id' => $playData['category_id'],
            'collection_id' => $playData['collection_id'],
            'lesson_id' => $playData['lesson_id'],
            'schedule_id' => $playData['schedule_id'],
            'lesson_type' => $playData['lesson_type'],
            'lesson_sub_id' => $playData['lesson_sub_id'],
            'ai_record_id' => $playData['ai_record_id'],
            'created_time' => time(),
            'duration' => $playData['duration'],
            'score' => $playData['score'],
            'midi' => $playData['midi'],
            'data' => json_encode($playData)
        ];

        $recordID =  PlayRecordModel::insertRecord($recordData);

        list($saveID, $playResult) = self::updateSave($userID, $playData);

        if (empty($saveID)) {
            return ['play_save_failure'];
        }
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
}