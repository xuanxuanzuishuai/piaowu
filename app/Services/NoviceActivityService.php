<?php


namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Models\CategoryV1Model;
use App\Models\GoodsV1Model;
use App\Models\PointActivityRecordModel;
use App\Models\StudentMedalModel;
use App\Models\StudentModelForApp;

class NoviceActivityService
{
    const NOVICE_ACTIVITY_CACHE_PRI_KEY = 'novice_activity_cache';
    //新手任务
    const NOVICE_ACTIVITY_EVENT_TYPE = 11;
    //奖励
    const NOVICE_ACTIVITY_AWARD = 'award';
    //额外奖励
    const NOVICE_ACTIVITY_ADDITIONAL_AWARD = 'additional_award';

    //新手任务完成情况
    const STUDENT_FINISH_TASK = 1;//学生完成任务，并且已领取额外奖励
    const STUDENT_NOT_FINISHED_TASK = 2;//学生未完成任务
    const STUDENT_NOT_FINISHED_TASK_NO_AWARD = 3;//学生完成任务未领取额外奖励


    /**
     * 获取积分活动列表
     * @param $studentId
     * @param $appVersion
     * @return mixed
     */
    public static function getNoviceActivityList($studentId, $appVersion)
    {
        //获取任务模版列表。
        $tmpCacheData = self::getActivityTemplate();
        if (empty($tmpCacheData)) {
            return [];
        }
        //获取新手任务配置
        list($noviceActivityType, $noviceActivityAdditionalType) = DictConstants::get(DictConstants::NOVICE_ACTIVITY_CONFIG, ['novice_activity_type', 'novice_activity_additional_type']);
        //获取当前用户参与活动记录
        $studentActivityRecord = self::getUserCompleteStatus($studentId);
        $studentActivityRecord = array_combine(array_column($studentActivityRecord, 'task_id'), $studentActivityRecord);
        //格式化模板数据
        foreach ($tmpCacheData as $item) {
            $award = json_decode($item['award'], true);
            $condition = json_decode($item['condition'], true);

            if (empty($condition['min_version']) || (AIPlayRecordService::isOldVersionApp($appVersion, $item['min_version']) != false)) {
                continue;
            }

            $tmpInfo = [
                'event_id' => $item['id'],
                'name' => $item['name'],
                'award_count' => $award['awards'][0]['amount'],
                'is_complete' => !empty($studentActivityRecord[$item['id']]),
                'complete_count' => !empty($studentActivityRecord[$item['id']]) ? Constants::STATUS_TRUE : Constants::STATUS_FALSE,
            ];

            if ($award['awards'][0]['type'] == CategoryV1Model::MEDAL_AWARD_TYPE) {
                $tmpInfo['thumb'] = !empty($item['thumb']) ? AliOSS::replaceCdnDomainForDss($item['thumb']) : '';
            }

            if ($item['type'] == $noviceActivityType) {
                $formatData[self::NOVICE_ACTIVITY_AWARD][] = $tmpInfo;
            } elseif($item['type'] == $noviceActivityAdditionalType) {
                $formatData[self::NOVICE_ACTIVITY_ADDITIONAL_AWARD][] = $tmpInfo;
            }
        }

        return $formatData ?? [];
    }

    /**
     * @param $studentId
     * @return mixed
     * 得到用户对新手任务的完成情况
     */
    public static function getUserCompleteStatus($studentId)
    {
        $taskId = DictConstants::get(DictConstants::NOVICE_ACTIVITY_CONFIG, 'novice_activity_task');
        $studentFinishNoviceActivity = PointActivityRecordModel::getStudentFinishNoviceActivity($studentId, $taskId);
        return $studentFinishNoviceActivity ?? [];
    }


    /**
     * @return mixed
     * 得到当前的活动模板
     */
    public static function getActivityTemplate()
    {
        $redis = RedisDB::getConn();
        return json_decode($redis->get(self::NOVICE_ACTIVITY_CACHE_PRI_KEY), true);
    }

    /**
     * 缓存活动模版
     * @param $date
     * @return bool
     */
    public static function createEventTaskCache($date)
    {
        $redis = RedisDB::getConn();
        $erp = new Erp();
        $eventTasksInfo = $erp->eventTaskList(0, self::NOVICE_ACTIVITY_EVENT_TYPE)['data'];
        if (empty($eventTasksInfo)) {
            SimpleLogger::error("novice activity event data empty,event_type=" . self::NOVICE_ACTIVITY_EVENT_TYPE, []);
            return false;
        }
        foreach ($eventTasksInfo[0]['tasks'] as $task) {
            $awards = json_decode($task['award'],true)['awards'][0];
            if ($awards['type'] == CategoryV1Model::MEDAL_AWARD_TYPE) {
                $thumbs = json_decode(GoodsV1Model::getById($awards['course_id'])['thumbs'], true);
                $task['thumb'] = $thumbs[0];
            }
            $taskDate[] = $task;
        }

        $key = self::NOVICE_ACTIVITY_CACHE_PRI_KEY;
        $redis->set($key, json_encode($taskDate));

        $medalData = [];
        foreach ($eventTasksInfo[0]['tasks'] as $task) {
            $awards = json_decode($task['award'],true)['awards'][0];
            if ($awards['type'] == CategoryV1Model::MEDAL_AWARD_TYPE) {
                $medalData[] = ['medal_id' => $awards['course_id'], 'task_desc' => $task['name']];
            }
        }
        //缓存奖章在弹窗的时候需要的信息
        if (!empty($medalData)) {
            $medalBaseInfo = array_column(GoodsV1Model::getMedalInfo(), NULL, 'medal_id');
            $medalCacheData = [];
            array_map(function ($medalInfo) use ($redis, $medalBaseInfo, &$medalCacheData, $date) {
                $medalKey = MedalService::getMedalInfoKey($medalInfo['medal_id'], $date);
                $medalBase = $medalBaseInfo[$medalInfo['medal_id']];
                $medalBase['task_desc'] = $medalInfo['task_desc'];
                $medalCacheData[$medalInfo['medal_id']] = json_encode($medalBase);
                $redis->hset($medalKey, MedalService::MEDAL_INFO_KEY, json_encode($medalBase));
            }, $medalData);
            SimpleLogger::error("set halloween award medal  cache data success", $eventTasksInfo);
        }
        return true;
    }


    /**
     * 新手任务上报
     * @param $studentId
     * @param $taskIds
     * @return bool
     * @throws RunTimeException
     */
    public static function noviceActivityReport($studentId, $taskIds)
    {
        $time = time();
        $date = date('Y-m-d', $time);
        //获取学生信息
        $studentInfo = StudentModelForApp::getById($studentId);
        if (empty($studentInfo)) {
            throw new RunTimeException(['students_is_required']);
        }
        //目标task数据
        if (empty($taskIds) || !is_array($taskIds)) {
            throw new RunTimeException(['event_task_info_error']);
        }
        //检测用户是否满足领奖条件
        $validTasksData = self::checkStudentAward($studentInfo, $taskIds);
        //用户已经获取的奖章
        $studentMedalInfo = StudentMedalModel::getRecords(['student_id' => $studentId]);
        if (!empty($studentMedalInfo)) {
            $studentMedalInfo = array_column($studentMedalInfo, 'array_column');
        }
        //发放奖励
        $erp = new Erp();
        array_map(function ($taskVal) use ($erp, $studentInfo, $studentMedalInfo) {
            //奖品是奖章需要记录额外信息
            if ($taskVal['award_info']['type'] == CategoryV1Model::MEDAL_AWARD_TYPE && !in_array($taskVal['award_info']['course_id'], $studentMedalInfo)) {
                //记录用户所得奖章类别信息
                $medalInfo = MedalService::getMedalIdInfo($taskVal['award_info']['course_id']);
                MedalService::updateStudentMedalCategoryInfo($studentInfo['id'], $medalInfo['category_id']);
                //记录用户所得奖章的详细信息
                StudentMedalModel::insertRecord(
                    [
                        'student_id' => $studentInfo['id'],
                        'medal_id' => $taskVal['award_info']['course_id'],
                        'medal_category_id' => $medalInfo['category_id'],
                        'task_id' => $taskVal['task_id'],
                        'create_time' => time(),
                        'report_log_id' => 0,
                        'is_show' => StudentMedalModel::IS_ACTIVE_SHOW
                    ]);
            }
            $taskResult = $erp->updateTask($studentInfo['uuid'], $taskVal['task_id'], ErpReferralService::EVENT_TASK_STATUS_COMPLETE);
            if (empty($taskResult['data'])) {
                throw new RunTimeException(['erp_create_user_event_task_award_fail']);
            }
        }, $validTasksData);

        //记录上报数据详情
        $recordInsertData = array_map(function ($taskId) use ($studentId, $date, $time) {
            return [
                'student_id' => $studentId,
                'task_id' => $taskId,
                'report_date' => $date,
                'create_time' => $time,
                'update_time' => $time,
            ];
        }, $taskIds);
        $insertRes = PointActivityRecordModel::batchInsert($recordInsertData, false);
        if ($insertRes === false) {
            throw new RunTimeException(['add_point_activity_record_fail']);
        }
        return true;
    }

    /**
     * 检测是否满足上报
     * @param $studentInfo
     * @param $taskIds
     * @return array
     */
    public static function checkStudentAward($studentInfo, $taskIds)
    {
        //获取任务模版
        $eventTaskInfo = self::getActivityTemplate();
        $eventTaskInfo = array_combine(array_column($eventTaskInfo, 'id'), $eventTaskInfo);
        //检测用户已完成的任务
        $erp = new Erp();
        $records = $erp->getUserTaskRelateInfo(['uuid' => $studentInfo['uuid'], 'task_id' => implode(',', $taskIds)])['data']['records'] ?? [];
        $relateInfo = array_column($records, 'status', 'event_task_id');

        foreach ($taskIds as $taskId) {
            //任务已完成禁止重复完成
            if ($relateInfo[$taskId] == ErpReferralService::EVENT_TASK_STATUS_COMPLETE) {
                continue;
            }
            //上报的任务ID是否正确
            if (!array_key_exists($taskId, $eventTaskInfo)) {
                continue;
            }
            $validTasks[] = [
                'task_id' => $taskId,
                'award_info' => json_decode($eventTaskInfo[$taskId]['award'], true)['awards'][0],
            ];
        }
        return $validTasks ?? [];
    }

    /**
     * 获取学生新手任务完成情况
     * @param $studentId
     * @param $version
     * @return array
     */
    public static function getStudentFinishTaskStatus($studentId, $version)
    {
        $studentFinishTask = [
            'finish_task_status' => Constants::STATUS_FALSE,
            'additional_award' => []
        ];
        $noviceActivityList = self::getNoviceActivityList($studentId, $version);
        if (empty($noviceActivityList)) {
           return $studentFinishTask;
        }
        $finishTask = true;
        $additionalAward = true;

        //查看新手任务是否完成
        foreach ($noviceActivityList['award'] as $item) {
            if (empty($item['is_complete'])) {
                $finishTask = false;
                break;
            }
        }
        //查看额外奖励是否领取
        foreach ($noviceActivityList['additional_award'] as $item) {
            if (empty($item['is_complete'])) {
                $additionalAward = false;
                break;
            }
        }

        if ($finishTask && $additionalAward) {
            $finishTaskStatus = self::STUDENT_FINISH_TASK;
        } elseif(!$finishTask && $additionalAward) {
            $finishTaskStatus = self::STUDENT_NOT_FINISHED_TASK;
        } elseif ($finishTask && !$additionalAward) {
            $finishTaskStatus = self::STUDENT_NOT_FINISHED_TASK_NO_AWARD;
        } else {
            $finishTaskStatus = self::STUDENT_NOT_FINISHED_TASK;
        }
        $additionalAward = $noviceActivityList['additional_award'];

        return [
        'finish_task_status' => $finishTaskStatus,
        'additional_award' => $additionalAward];
    }
}