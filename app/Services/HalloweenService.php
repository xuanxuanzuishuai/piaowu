<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/09/28
 * Time: 5:29 PM
 */

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\ActivitySignUpModel;
use App\Models\AIPlayRecordCHModel;
use App\Models\AIPlayRecordModel;
use App\Models\CategoryV1Model;
use App\Models\GoodsV1Model;
use App\Models\ReviewCourseModel;
use App\Models\StudentModel;
use App\Models\StudentModelForApp;

class HalloweenService
{
    //event活动类型
    const HALLOWEEN_EVENT_TYPE = 9;
    //event活动缓存前缀
    const HALLOWEEN_CACHE_PRI_KEY = 'halloween_cache_';
    //万圣节活动里程排行榜缓存key
    const HALLOWEEN_RANK_CACHE_PRI_KEY = 'halloween_rank_cache';
    const HALLOWEEN_RANK_CACHE_EXPIRE_TIME = 900;
    //event配置field
    const HALLOWEEN_EVENT_FIELD = 'event';
    //task配置field
    const HALLOWEEN_TASK_FIELD = 'task';
    //额外/排行奖励信息配置field
    const HALLOWEEN_AWARD_FIELD = 'award';

    /**
     * 获取万圣节用户参与数据
     * @param $studentId
     * @return array
     * @throws RunTimeException
     */
    public static function halloweenUserRecord($studentId)
    {
        //活动配置数据
        $halloweenConfig = DictConstants::getSet(DictConstants::HALLOWEEN_CONFIG);
        //检测用户是否报名
        $signInfo = ActivitySignUpModel::getRecord(
            [
                'user_id' => $studentId,
                'event_id' => $halloweenConfig['halloween_event'],
                'status' => ActivitySignUpModel::STATUS_ABLE
            ],
            ['create_time', 'complete_time'], false);
        if (empty($signInfo)) {
            throw new RunTimeException(['user_not_join_halloween']);
        }
        //当天的有效里程
        $completeProcess = self::getStudentDayMileagesStaticsData($studentId, $halloweenConfig['halloween_event']);
        //获取里程统计数据
        $completeProcess['total_mileages'] = self::getStudentMileagesStaticsData($studentId)['mileages'];
        //获取里程进行过程数据
        $completeProcess['process'] = self::getStudentEventTaskProcessStatus($halloweenConfig['halloween_event'], $halloweenConfig['process_task_type'], $studentId);
        return $completeProcess;
    }

    /**
     * 获取用户个人当天的里程统计数据
     * @param $studentId
     * @param $eventId
     * @return array|mixed
     */
    public static function getStudentDayMileagesStaticsData($studentId, $eventId)
    {
        $nowTime = time();
        $eventInfo = self::getEventTaskCache($eventId, self::HALLOWEEN_EVENT_FIELD, date('Y-m-d'));
        list($startTime, $endTime) = Util::getStartEndTimestamp($nowTime);
        $todayRecord = AIPlayRecordCHModel::getStudentValidTotalDuration($studentId, $startTime, $endTime, $eventInfo['every_day_valid_seconds']);
        return [
            'today_mileages' => floor($todayRecord[0]['sum_duration'] / 60),
            'day_valid_mileages' => $eventInfo['every_day_valid_seconds'] / 60,
            'remaining_hours' => floor(($eventInfo['end_time'] - $nowTime) % Util::TIMESTAMP_ONEDAY / Util::TIMESTAMP_1H),
            'remaining_days' => floor(($eventInfo['end_time'] - $nowTime) / Util::TIMESTAMP_ONEDAY),
            'start_time' => $eventInfo['start_time'],
            'end_time' => $eventInfo['end_time'],
        ];
    }

    /**
     * 获取里程进行过程数据
     * @param $eventID
     * @param $taskType
     * @param $studentId
     * @return array
     */
    public static function getStudentEventTaskProcessStatus($eventID, $taskType, $studentId)
    {
        $studentInfo = StudentModelForApp::getById($studentId);
        $eventTasks = self::getEventTaskCache($eventID, self::HALLOWEEN_TASK_FIELD, date('Y-m-d'), $taskType);
        $completeInfo = [];
        if (!empty($eventTasks)) {
            $taskIds = array_column($eventTasks, 'id');
            $erp = new Erp();
            $records = $erp->getUserTaskRelateInfo(['uuid' => $studentInfo['uuid'], 'task_id' => implode(',', $taskIds)])['data']['records'] ?? [];
            $relateInfo = array_column($records, 'status', 'event_task_id');
            foreach ($eventTasks as $tk => $task) {
                $award = json_decode($task['award'], true)['awards'][0];
                $completeInfo[$tk] = [
                    'task_id' => $task['id'],
                    'valid_num' => json_decode($task['condition'], true)['valid_num'] / 60,
                    'award_type' => $award['type'],
                    'award_amount' => $award['amount'],
                    'has_draw' => (isset($relateInfo[$task['id']]) && $relateInfo[$task['id']] == ErpReferralService::EVENT_TASK_STATUS_COMPLETE)
                ];
                //获取奖章数据
                if ($award['type'] == CategoryV1Model::MEDAL_AWARD_TYPE) {
                    $redis = RedisDB::getConn();
                    $medalInfo = json_decode($redis->hget(MedalService::MEDAL_INFO_KEY, $award['course_id']), true);
                    $medalInfo['thumbs'] = AliOSS::replaceCdnDomainForDss(json_decode($medalInfo['thumbs'], true)[0]);
                    $medalInfo['medal_desc'] = (is_null($medalInfo['medal_level']) || $medalInfo['medal_level'] <= 1) ? '获得新奖章' : '奖章升级';
                    $completeInfo[$tk]['medal_info'] = $medalInfo;
                }
            }
            array_multisort(array_column($completeInfo, 'valid_num'), SORT_ASC, $completeInfo);
        }
        return $completeInfo;
    }


    /**
     * 万圣节排行榜数据
     * @param $studentId
     * @param $rankLimit
     * @return array
     */
    public static function halloweenRank($studentId, $rankLimit)
    {
        $rankData = self::getHalloweenRankCache(0, $rankLimit);
        //查询排行榜中学生的数据
        $rankList = $tmpRankList = [];
        $studentIds = array_keys($rankData);
        //用户是否进入排行榜:true入榜 false未入榜
        $studentIsInRank = true;
        if (!array_key_exists($studentId, $rankData)) {
            array_push($studentIds, $studentId);
            $studentIsInRank = false;
        }
        $studentsInfo = array_column(StudentModel::getRecords(['id' => $studentIds], ['name', 'thumb', 'has_review_course', 'id', 'uuid']), null, 'id');
        if (!empty($rankData)) {
            $i = 1;
            array_walk($rankData, function ($mileages, $sId) use (&$tmpRankList, &$i, $studentsInfo) {
                $tmpRankList['rank_list'][$sId] = [
                    'name' => $studentsInfo[$sId]['name'],
                    'thumb' => $studentsInfo[$sId]['thumb'] ? AliOSS::replaceCdnDomainForDss($studentsInfo[$sId]['thumb']) : AliOSS::replaceCdnDomainForDss(DictConstants::get(DictConstants::STUDENT_DEFAULT_INFO, 'default_thumb')),
                    'medal_thumb' => StudentServiceForApp::getStudentShowMedal($sId),
                    'mileages' => floor($mileages / 60),
                    'rank_num' => $i,
                ];
                $i++;
            });
        }
        $rankData = self::getStudentAwardInfoByRank($tmpRankList['rank_list'], $studentsInfo);
        //获取用户自己的排名信息
        if ($studentIsInRank == true) {
            //获取奖品领取状态
            $rankList['self'] = self::checkSelfAwardStatus($rankData[$studentId], $studentsInfo[$studentId]);
        } else {
            $rankList['self'] = self::getStudentMileagesStaticsData($studentId);
            //获取距离最后一名的里程数
            $rankList['self']['space'] = end($rankData)['mileages'] - $rankList['self']['mileages'];
        }
        $rankList['rank_list'] = array_values($rankData);
        return $rankList;
    }

    /**
     * 检测奖品的领取状态
     * @param $awardData
     * @param $studentInfo
     * @return array
     */
    private static function checkSelfAwardStatus($awardData, $studentInfo)
    {
        $rankAwardTaskIds = array_column($awardData['rank_award'], 'task_id');
        $extraAwardTaskIds = array_column($awardData['extra_award'], 'task_id');
        $erp = new Erp();
        $records = $erp->getUserTaskRelateInfo(['uuid' => $studentInfo['uuid'], 'task_id' => implode(',', array_merge($rankAwardTaskIds, $extraAwardTaskIds))])['data']['records'] ?? [];
        $relateInfo = array_column($records, 'status', 'event_task_id');
        array_walk($awardData['rank_award'], function (&$rlv, $rlk) use ($relateInfo) {
            $rlv['has_draw'] = (isset($relateInfo[$rlv['task_id']]) && $relateInfo[$rlv['task_id']] == ErpReferralService::EVENT_TASK_STATUS_COMPLETE);
        });
        array_walk($awardData['extra_award'], function (&$elv, $elk) use ($relateInfo) {
            $elv['has_draw'] = (isset($relateInfo[$elv['task_id']]) && $relateInfo[$elv['task_id']] == ErpReferralService::EVENT_TASK_STATUS_COMPLETE);
        });
        return $awardData;
    }

    /**
     * 通过排行名次获取用户奖励数据
     * @param $rankList
     * @param $studentInfo
     * @return array
     */
    public static function getStudentAwardInfoByRank($rankList, $studentInfo)
    {
        $halloweenConfig = DictConstants::getSet(DictConstants::HALLOWEEN_CONFIG);
        $rankAward = self::getEventTaskCache($halloweenConfig['halloween_event'], self::HALLOWEEN_AWARD_FIELD, date('Y-m-d'));
        //查询实物奖品详细信息
        $extraAwardData = [];
        array_walk($rankList, function (&$rav, $rak) use (&$extraAwardData, $halloweenConfig, $rankAward, $studentInfo) {
            //排行任务奖励
            $rav['rank_award'] = $rankAward[$halloweenConfig['rank_task_type']][$rav['rank_num']];
            //年卡用户获取额外任务奖励
            $rav['extra_award'] = [];
            if ($studentInfo[$rak]['has_review_course'] == ReviewCourseModel::REVIEW_COURSE_1980) {
                $rav['extra_award'] = $rankAward[$halloweenConfig['extra_task_type']][$rav['rank_num']];
            }
        });
        return $rankList;
    }


    /**
     * 获取用户个人的里程统计数据
     * @param $studentId
     * @return array
     */
    public static function getStudentMileagesStaticsData($studentId)
    {
        $studentsInfo = StudentModelForApp::getById($studentId);
        //配置的活动ID
        $halloweenConfig = DictConstants::getSet(DictConstants::HALLOWEEN_CONFIG);
        //获取配置活动的详细信息
        $eventInfo = self::getEventTaskCache($halloweenConfig['halloween_event'], self::HALLOWEEN_EVENT_FIELD, date('Y-m-d'));
        //获取里程统计数据
        $selfRankData = AIPlayRecordCHModel::getStudentValidTotalDuration($studentId, $eventInfo['start_time'], $eventInfo['end_time'], $eventInfo['every_day_valid_seconds']);
        $rankNum = self::getStudentHalloweenRankNum($studentId);
        return [
            'name' => $studentsInfo[$studentId]['name'],
            'thumb' => $studentsInfo[$studentId]['thumb'] ? AliOSS::replaceCdnDomainForDss($studentsInfo[$studentId]['thumb']) : AliOSS::replaceCdnDomainForDss(DictConstants::get(DictConstants::STUDENT_DEFAULT_INFO, 'default_thumb')),
            'medal_thumb' => StudentServiceForApp::getStudentShowMedal($studentId),
            'mileages' => floor(array_sum(array_column($selfRankData, 'sum_duration')) / 60),
            'rank_num' => $rankNum,
        ];
    }

    /**
     * 生成event事件数据缓存key
     * @param $eventId
     * @param $dateNum
     * @return string
     */
    public static function getCacheKey($eventId, $dateNum = NULL)
    {
        $date = empty($dateNum) ? date('Ymd') : $dateNum;
        return self::HALLOWEEN_CACHE_PRI_KEY . $date . '_' . $eventId;
    }

    /**
     * 生成排行榜缓存key
     * @return string
     */
    public static function getRankCacheKey()
    {
        return self::HALLOWEEN_RANK_CACHE_PRI_KEY;
    }

    /**
     * 设置事件任务模板数据
     * @param $date
     * @return bool
     */
    public static function setEventTaskCache($date)
    {
        //获取event数据
        $goodsData = $medalData = [];
        $redis = RedisDB::getConn();
        $time = time();
        $erp = new Erp();
        $eventTasksInfo = $erp->eventTaskList(0, self::HALLOWEEN_EVENT_TYPE)['data'];
        if (empty($eventTasksInfo)) {
            SimpleLogger::error("halloween event data empty,event_type=" . self::HALLOWEEN_EVENT_TYPE, []);
            return false;
        }
        $halloweenConfig = DictConstants::getSet(DictConstants::HALLOWEEN_CONFIG);
        //设置过期
        $expireTime = strtotime('+2 days') - $time;
        array_map(function ($item) use ($redis, $date, $expireTime, $halloweenConfig, &$goodsData, &$medalData) {
            $key = self::getCacheKey($item['id'], $date);
            //event基础配置信息
            $hashData = [];
            $hashData[self::HALLOWEEN_EVENT_FIELD] = $item['settings'];
            $hashData[self::HALLOWEEN_TASK_FIELD] = json_encode($item['tasks']);
            //获取排行和额外任务奖励
            $awardData = [];
            foreach ($item['tasks'] as $tk => $tv) {
                //整合排行奖励和额外奖励
                $awardInfo = json_decode($tv['award'], true);
                $condition = json_decode($tv['condition'], true);
                foreach ($awardInfo['awards'] as $awk => $awv) {
                    $goodsId = $awv['course_id'];
                    //收集奖励是奖章的award数据
                    if ($awv['type'] == CategoryV1Model::MEDAL_AWARD_TYPE) {
                        $medalData[] = ['medal_id' => $goodsId, 'task_desc' => $tv['name']];
                    }
                    if (empty($goodsData[$goodsId]) && !empty($goodsId)) {
                        //获取实物奖励数据详情
                        $goodsData[$goodsId] = GoodsV1Model::getById($goodsId);
                    }
                    if (in_array($tv['type'], [$halloweenConfig['rank_task_type'], $halloweenConfig['extra_task_type']])) {
                        for ($i = $condition['min_rank']; $i <= $condition['max_rank']; $i++) {
                            $awardData[$tv['type']][$i][] = [
                                'type' => $awv['type'],
                                'goods_id' => $goodsId,
                                'task_id' => $tv['id'],
                                'goods_name' => $goodsId ? $goodsData[$goodsId]['name'] : null,
                                'goods_thumb' => $goodsId ? AliOSS::replaceCdnDomainForDss(json_decode($goodsData[$goodsId]['thumbs'], true)[0]) : null,
                                'amount' => $awv['amount'],
                            ];
                        }
                    }
                }
            }
            $hashData[self::HALLOWEEN_AWARD_FIELD] = json_encode($awardData);
            $redis->hmset($key, $hashData);
            $redis->expire($key, $expireTime);
        }, $eventTasksInfo);
        SimpleLogger::error("set halloween event cache data success", $eventTasksInfo);
        //缓存奖章在弹窗的时候需要的信息
        $medalBaseInfo = array_column(GoodsV1Model::getMedalInfo(), NULL, 'medal_id');
        $medalCacheKey = MedalService::MEDAL_INFO_KEY;
        $medalCacheData = [];
        array_map(function ($medalInfo) use ($redis, $medalBaseInfo, &$medalCacheData) {
            $medalBase = $medalBaseInfo[$medalInfo['medal_id']];
            $medalBase['task_desc'] = $medalInfo['task_desc'];
            $medalCacheData[$medalInfo['medal_id']] = json_encode($medalBase);
        }, $medalData);
        $redis->hmset($medalCacheKey, $medalCacheData);
        $redis->expire($medalCacheKey, $expireTime);
        SimpleLogger::error("set halloween award medal  cache data success", $eventTasksInfo);
        return true;
    }

    /**
     * 获取缓存数据
     * @param $eventId
     * @param $field
     * @param $date
     * @param $taskType
     * @return mixed
     */
    private static function getEventTaskCache($eventId, $field, $date, $taskType = '')
    {
        $cacheKey = self::getCacheKey($eventId, $date);
        $redis = RedisDB::getConn();
        if (empty($redis->hexists($cacheKey, $field))) {
            self::setEventTaskCache($date);
        }
        $cacheData = $redis->hget($cacheKey, $field);
        if (empty($cacheData)) {
            return [];
        }
        //获取不同任务类型的数据
        $data = json_decode($cacheData, true);
        if ($field == self::HALLOWEEN_TASK_FIELD && !empty($taskType)) {
            $filterData = [];
            array_map(function ($cv) use ($taskType, &$filterData) {
                if ($cv['type'] == $taskType) {
                    $filterData[] = $cv;
                }
            }, $data);
            return $filterData;
        } else {
            return $data;
        }
    }


    /**
     * 设置万圣节里程排行榜数据:定时任务脚本每10分钟更新一次
     */
    public static function setHalloweenRankCache()
    {
        //删除缓存数据
        $redis = RedisDB::getConn();
        $cacheKey = self::getRankCacheKey();
        $redis->del([$cacheKey]);
        //配置的活动ID
        $halloweenConfig = DictConstants::getSet(DictConstants::HALLOWEEN_CONFIG);
        //获取配置活动的详细信息
        $eventInfo = self::getEventTaskCache($halloweenConfig['halloween_event'], self::HALLOWEEN_EVENT_FIELD, date('Y-m-d'));
        //获取里程统计数据
        $rankData = AIPlayRecordCHModel::getHalloweenDurationRank($halloweenConfig['halloween_event'], $eventInfo['every_day_valid_seconds'], $eventInfo['start_time'], $eventInfo['end_time'], $halloweenConfig['rank_limit']);
        if (!empty($rankData)) {
            $redis->zadd($cacheKey, array_column($rankData, 'user_total_du', 'student_id'));
            $redis->expire($cacheKey, self::HALLOWEEN_RANK_CACHE_EXPIRE_TIME);
        }
    }

    /**
     * 获取万圣节里程排行榜数据
     * @param int $start
     * @param string $stop
     * @return array
     */
    private static function getHalloweenRankCache($start = 0, $stop = '-1')
    {
        $redis = RedisDB::getConn();
        $cacheKey = self::getRankCacheKey();
        if (empty($redis->exists($cacheKey))) {
            self::setHalloweenRankCache();
        }
        return $redis->zrevrange($cacheKey, $start, $stop, ['WITHSCORES' => true]);
    }

    /**
     * 获取学生的排行榜排名
     * @param $studentId
     * @return int
     */
    private static function getStudentHalloweenRankNum($studentId)
    {
        $redis = RedisDB::getConn();
        $cacheKey = self::getRankCacheKey();
        $rankNum = $redis->ZREVRANK($cacheKey, $studentId);
        if (!is_null($rankNum)) {
            $rankNum += 1;
        }
        return $rankNum;
    }

    /**
     * 记录万圣节每日有效练琴时长数据
     * @param $studentId
     * @param $stepDuration
     * @return bool
     */
    public static function studentHalloweenCompleteTime($studentId, $stepDuration)
    {
        //获取万圣节事件的配置信息
        $time = time();
        $halloweenConfig = DictConstants::getSet(DictConstants::HALLOWEEN_CONFIG);
        $eventInfo = self::getEventTaskCache($halloweenConfig['halloween_event'], self::HALLOWEEN_EVENT_FIELD, date('Y-m-d'));
        if (empty($eventInfo) || ($eventInfo['start_time'] > $time) || ($eventInfo['end_time'] < $time)) {
            SimpleLogger::info("halloween event info empty", ['event_id' => $halloweenConfig['halloween_event']]);
            return false;
        }
        $todayTotalDuration = AIPlayRecordModel::getDailyDurationCache($studentId);
        if ($todayTotalDuration > $eventInfo['every_day_valid_seconds']) {
            SimpleLogger::info("duration gt student day valid seconds", ['duration' => $todayTotalDuration]);
            return false;
        }
        //记录每日有效时长完成的时间
        $updateData = [
            'complete_time' => $time,
            'update_time' => $time,
            'complete_mileages[+]' => $stepDuration,
        ];
        $db = MysqlDB::getDB();
        $affectRows = $db->updateGetCount(ActivitySignUpModel::$table, $updateData, ['user_id' => $studentId, 'event_id' => $halloweenConfig['halloween_event']]);
        if (empty($affectRows)) {
            SimpleLogger::info("halloween complete time update fail", ['update_data' => $updateData, 'where' => ['user_id' => $studentId, 'event_id' => $halloweenConfig['halloween_event']]]);
            return false;
        }
        return true;
    }

    /**
     * 万圣节领取奖励
     * @param $studentId
     * @param $taskIds
     * @return bool
     * @throws RunTimeException
     */
    public static function halloweenTakeAward($studentId, $taskIds)
    {
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
        $validTasksData = self::checkStudentCheckAwardQuality($taskIds, $studentInfo);
        //发放奖励
        $erp = new Erp();
        array_map(function ($taskIdVal) use ($erp, $studentInfo) {
            $takeRes = $erp->updateTask($studentInfo['uuid'], $taskIdVal, ErpReferralService::EVENT_TASK_STATUS_COMPLETE);
            if ($takeRes === false) {
                throw new RunTimeException(['take_event_task_award_fail']);
            }
        }, $validTasksData);
        return true;
    }


    /**
     * 检测用户是否满足领奖条件
     * @param $taskIds
     * @param $studentInfo
     * @return array
     * @throws RunTimeException
     */
    private static function checkStudentCheckAwardQuality($taskIds, $studentInfo)
    {
        //获取活动配置数据
        $halloweenConfig = DictConstants::getSet(DictConstants::HALLOWEEN_CONFIG);
        $eventTasks = self::getEventTaskCache($halloweenConfig['halloween_event'], self::HALLOWEEN_TASK_FIELD, date('Y-m-d'));
        if (empty($eventTasks)) {
            throw new RunTimeException(['halloween_event_task_error']);
        }
        $taskList = array_column($eventTasks, null, 'id');
        $eventTaskIdList = array_keys($taskList);
        //检测活动task是否已完成：禁止重复领取
        $erp = new Erp();
        $records = $erp->getUserTaskRelateInfo(['uuid' => $studentInfo['uuid'], 'task_id' => implode(',', $taskIds)])['data']['records'] ?? [];
        $relateInfo = array_column($records, 'status', 'event_task_id');
        //检测用户是否满足领奖条件
        $validTasks = [];
        foreach ($taskIds as $taskId) {
            //任务已完成禁止重复完成
            if ($relateInfo[$taskId] == ErpReferralService::EVENT_TASK_STATUS_COMPLETE) {
                continue;
            }
            if (!in_array($taskId, $eventTaskIdList)) {
                continue;
            }
            $takeAwardTask = $taskList[$taskId];
            $condition = json_decode($takeAwardTask['condition'], true);
            $eventInfo = self::getEventTaskCache($halloweenConfig['halloween_event'], self::HALLOWEEN_EVENT_FIELD, date('Y-m-d'));
            if ($takeAwardTask['type'] == $halloweenConfig['process_task_type']) {
                //游行阶段任务
                //获取活动期间用户有效的里程数据
                $aiPlayRecord = AIPlayRecordCHModel::getStudentValidTotalDuration($studentInfo['id'], $eventInfo['start_time'], $eventInfo['end_time'], $eventInfo['every_day_valid_seconds']);
                $totalMileages = array_sum(array_column($aiPlayRecord, 'sum_duration'));
                if ($condition['valid_num'] > $totalMileages) {
                    SimpleLogger::error('process task student not reach valid num', ['task_id' => $taskId, 'valid_num' => $condition['valid_num'], 'total_mileages' => $totalMileages, 'student_id' => $studentInfo['id']]);
                    throw new RunTimeException(['not_reach_valid_num']);
                }
            } else {
                //排行榜任务&额外任务
                //获取用户排名
                $rankNum = self::getStudentHalloweenRankNum($studentInfo['id']);
                if (is_null($rankNum) || ($rankNum > $halloweenConfig['take_award_rank_limit'])) {
                    SimpleLogger::error('student rank num invalid', ['task_id' => $taskId, 'rank_num' => $rankNum, 'take_award_rank_limit' => $halloweenConfig['take_award_rank_limit'], 'student_id' => $studentInfo['id']]);
                    throw new RunTimeException(['not_reach_valid_rank_num']);
                }
                //检测当前排名是否可领取目标奖励task
                if (($rankNum < $condition['min_rank']) || ($rankNum > $condition['max_rank'])) {
                    SimpleLogger::error('student rank num invalid', ['task_id' => $taskId, 'rank_num' => $rankNum, 'min_rank' => $condition['min_rank'], 'max_rank' => $condition['max_rank'], 'student_id' => $studentInfo['id']]);
                    throw new RunTimeException(['task_rank_num_invalid']);
                }
                //额外任务需要验证学生是否是年卡用户
                if (($takeAwardTask['type'] == $halloweenConfig['extra_task_type']) && ($studentInfo['has_review_course'] != ReviewCourseModel::REVIEW_COURSE_1980)) {
                    SimpleLogger::error('student is not normal', ['task_id' => $taskId, 'has_review_course' => $studentInfo['has_review_course'], 'student_id' => $studentInfo['id']]);
                    throw new RunTimeException(['task_student_status_invalid']);
                }
            }
            $validTasks[] = $taskId;
        }
        if (empty($validTasks)) {
            throw new RunTimeException(['halloween_event_task_invalid']);
        }
        return $validTasks;
    }
}