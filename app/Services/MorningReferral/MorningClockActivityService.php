<?php
/**
 * 清晨5日打卡活动
 */

namespace App\Services\MorningReferral;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Morning;
use App\Libs\MorningDictConstants;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dawn\DawnLeadsModel;
use App\Models\MessagePushRulesModel;
use App\Models\MorningSharePosterModel;
use App\Models\MorningTaskAwardModel;
use App\Models\OperationActivityModel;
use App\Models\SharePosterModel;
use App\Models\WeChatConfigModel;
use App\Services\SharePosterService;

class MorningClockActivityService
{
    // 5日打卡活动解锁状态
    const TASK_STATUS_LOCK           = 0;     // 待解锁、未解锁
    const TASK_STATUS_PROGRESS       = 4; // 进行中
    const TASK_STATUS_WAIT_VERIFY    = 1;  // 等待审核、审核中
    const TASK_STATUS_VERIFY_SUCCESS = 2;  // 审核通过 - 已打卡
    const TASK_STATUS_VERIFY_FAIL    = 3;  // 审核未通过

    /**
     * 5日打卡活动详情
     * @param $studentUuid
     * @return array
     */
    public static function getClockActivityIndex($studentUuid)
    {
        $returnData = [
            'is_join'    => 0, // 0：不能参与，
            'award_node' => [],  // 奖励节点
            'node_list'  => [],  // 节点
        ];
        // 班级信息
        $collInfo = DawnLeadsModel::getRecord(['uuid' => $studentUuid]);
        if (empty($collInfo) || empty($collInfo['collection_id'])) {
            SimpleLogger::info("getCollectionActivityDetail_student_collection_is_empty", [$studentUuid]);
            return $returnData;
        }
        $returnData['is_join'] = true;
        list($awardNode, $nodeNum) = MorningDictConstants::get(MorningDictConstants::MORNING_FIVE_DAY_ACTIVITY, ['5day_award_node', '5day_node_num']);
        $awardNode = json_decode($awardNode, true);
        // 获取奖励列表
        $studentAwardList = MorningTaskAwardModel::getStudentFiveDayAwardList($studentUuid);
        $studentAwardListByDay = [];
        foreach ($studentAwardList as $item) {
            $ext = !empty($item['ext']) ? json_decode($item['ext'], true) : [];
            $studentAwardListByDay[$ext['day']] = $item;
        }
        unset($item);
        // 获取奖励状态
        foreach ($awardNode as $item) {
            $award = $studentAwardListByDay[$item['day']] ?? [];
            $awardStatus = $award['status'] ?? OperationActivityModel::SEND_AWARD_STATUS_NOT_OWN;
            $returnData['award_node'][] = [
                'day'       => $item['day'],
                'award_num' => $item['award_num'],
                'status'    => self::getCollectionActivityStatus($awardStatus),
            ];
        }
        // 获取学生练习信息
        $lessonList = (new Morning())->getStudentLessonSchedule([$studentUuid])[$studentUuid] ?? [];
        $lessonData = self::getLessonDoneStep($lessonList);
        // 获取参与记录
        $sharePosterList = MorningSharePosterModel::getFiveDayUploadSharePosterList($studentUuid);
        $sharePosterTask = [];
        foreach ($sharePosterList as $item) {
            empty($sharePosterTask[$item['task_num']]) && $sharePosterTask[$item['task_num']] = $item;
        }
        unset($item);
        // 打卡进度
        for ($i = 1; $i <= $nodeNum; $i++) {
            $tmpData = [
                'day'         => $i,
                'is_unlock'   => Constants::STATUS_FALSE,
                'task_status' => self::TASK_STATUS_LOCK,
                'unlock_time' => date("Y-m-d 09:i:s", time() + Util::TIMESTAMP_ONEDAY * $i),
            ];
            // 是否满足解锁条件
            // 是否已完成曲目的练习
            $dayInfo = $lessonData[$i - 1] ?? [];
            $isDone = $dayInfo['status'] == Constants::STATUS_TRUE;
            // 是否已经过了9点
            $isNine = date("G") - 9 >= 0;
            if ($isDone && $isNine) {
                $tmpData['is_unlock'] = Constants::STATUS_TRUE;
            }
            // 是否已参与
            $daySharePoster = $sharePosterTask[$i] ?? [];
            if (!empty($daySharePoster)) {
                if ($daySharePoster['verify_status'] == SharePosterModel::VERIFY_STATUS_WAIT) {
                    $tmpData['task_status'] = self::TASK_STATUS_WAIT_VERIFY;
                } elseif ($daySharePoster['verify_status'] == SharePosterModel::VERIFY_STATUS_QUALIFIED) {
                    $tmpData['task_status'] = self::TASK_STATUS_VERIFY_SUCCESS;
                } elseif ($daySharePoster['verify_status'] == SharePosterModel::VERIFY_STATUS_UNQUALIFIED) {
                    $tmpData['task_status'] = self::TASK_STATUS_VERIFY_FAIL;
                }
            } else {
                $tmpData['task_status'] = self::TASK_STATUS_PROGRESS;
            }
            $returnData['node_list'][] = $tmpData;
        }
        return $returnData;
    }

    /**
     * 获取5日打卡活动奖励发放状态
     * @param $status
     * @return int
     */
    public static function getCollectionActivityStatus($status)
    {
        if (in_array($status, [OperationActivityModel::SEND_AWARD_STATUS_WAITING, OperationActivityModel::SEND_AWARD_STATUS_GIVE_FAIL])) {
            $sendStatus = OperationActivityModel::SEND_AWARD_STATUS_WAITING;
        } elseif (in_array($status, [OperationActivityModel::SEND_AWARD_STATUS_GIVE, OperationActivityModel::SEND_AWARD_STATUS_GIVE_ING,])) {
            $sendStatus = OperationActivityModel::SEND_AWARD_STATUS_GIVE;
        } else {
            $sendStatus = OperationActivityModel::SEND_AWARD_STATUS_NOT_OWN;
        }
        return $sendStatus;
    }

    /**
     * 获取课程曲目完成情况
     * @param $lessonList
     * @return array
     */
    public static function getLessonDoneStep($lessonList)
    {
        $returnData = [];
        if (empty($lessonList)) return $returnData;
        foreach ($lessonList as $lesson) {
            foreach ($lesson as $key => $item) {
                // 课程状态 1 待解锁 2 待学习 3 已学习 6 学习中
                if ($item['status'] == 2) {
                    // 曲目完成练习
                    $_tmpData['status'] = Constants::STATUS_TRUE;
                } else {
                    $_tmpData['status'] = Constants::STATUS_FALSE;
                }
                $returnData[] = $_tmpData;
            }
            unset($key, $item);
            // 只处理一个课程的练习曲目， 如果后续增加了练琴曲目，那再调整
            break;
        }
        return $returnData;
    }

    /**
     * 获取5日打卡活动某一天的情况
     * @param $studentUuid
     * @param $params
     * @return array
     * @throws RunTimeException
     */
    public static function getClockActivityDayDetail($studentUuid, $params)
    {
        // 检查当天是否已经解锁  有分班，当天练琴完成
        self::checkClockActivityIsLock($studentUuid, $params['day']);
        // 获取参与记录
        $sharePosterList = MorningSharePosterModel::getFiveDayUploadSharePosterList($studentUuid);
        $sharePosterRecord = [];
        foreach ($sharePosterList as $item) {
            if ($item['task_num'] == $params['day']) {
                $sharePosterRecord = $item;
                break;
            }
        }
        unset($item);
        // 组装数据
        $returnData = [
            'day'                  => $params['day'],
            'task_status'          => self::TASK_STATUS_PROGRESS,
            'format_verify_time'   => '',
            'format_verify_reason' => [],
            'share_poster_url'     => '',
        ];
        if (!empty($sharePosterRecord)) {
            $returnData['format_verify_time'] = !empty($sharePosterRecord['verify_time']) ? date("Y-m-d H:i:s", $sharePosterRecord['verify_time']) : '';
            $returnData['task_status'] = $sharePosterRecord['verify_status'];
            $returnData['share_poster_url'] = AliOSS::replaceCdnDomainForDss($sharePosterRecord['image_path']);
            if ($sharePosterRecord['verify_status'] == SharePosterModel::VERIFY_STATUS_UNQUALIFIED) {
                $returnData['format_verify_reason'] = SharePosterService::reasonToStr($sharePosterRecord['verify_status']);
                !empty($sharePosterRecord['remark']) && $returnData['format_verify_reason'][] = $sharePosterRecord['remark'];
            }
        }
        return $returnData;
    }

    /**
     * 检查打开功能是否解锁
     * @param $studentUuid
     * @param $day
     * @return void
     * @throws RunTimeException
     */
    public static function checkClockActivityIsLock($studentUuid, $day)
    {
        // 检查当天是否已经解锁  有分班，当天练琴完成
        // 班级信息
        $collInfo = DawnLeadsModel::getRecord(['uuid' => $studentUuid]);
        if (empty($collInfo) || empty($collInfo['collection_id'])) {
            throw new RunTimeException(['student_collection_is_empty']);
        }
        // 获取学生练习信息
        $lessonList = (new Morning())->getStudentLessonSchedule([$studentUuid])[$studentUuid] ?? [];
        $lessonData = self::getLessonDoneStep($lessonList);
        $dayLesson = $lessonData[$day - 1] ?? [];
        // 学生是否练琴
        if (empty($dayLesson) || $dayLesson['status'] != Constants::STATUS_TRUE) {
            throw new RunTimeException(['morning_clock_activity_no_play']);
        }
    }

    /**
     * 获取5日打卡海报分享语
     * @param $studentUuid
     * @param $params
     * @return array
     * @throws RunTimeException
     */
    public static function getClockActivityShareWord($studentUuid, $params)
    {
        $day = $params['day'];
        // 判断是否解锁
        self::checkClockActivityIsLock($studentUuid, $day);
        // 获取邀请语信息
        $message = self::generateClockActivityRuleMsgPoster($studentUuid, $day);
        if (!empty($message['cotent']) && is_array($message['cotent'])) {
            foreach ($message['cotent'] as $item) {
                if ($item['key'] == 'content_2' && $item['type'] == 1) {
                    $shareWord = $item['value'];
                } elseif ($item['type'] == 2) {
                    $sharePosterUrl = $item['path'];
                }
            }
        }
        // 返回数据
        return [
            'share_word'       => isset($shareWord) ? Util::textDecode($shareWord) : '',
            'share_poster_url' => isset($sharePosterUrl) ? Util::textDecode($sharePosterUrl) : '',
        ];
    }

    /**
     * 5日打上传图片
     * @param $studentUuid
     * @param $params
     * @return array
     * @throws RunTimeException
     */
    public static function clockActivityUpload($studentUuid, $params)
    {
        $day = $params['day'];
        $time = time();
        // 检查是否解锁 - 只有解锁了才可以上传
        self::checkClockActivityIsLock($studentUuid, $day);
        // 检查是否已经上传
        $record = MorningSharePosterModel::getFiveDayUploadSharePosterByTask($studentUuid, $day)[0] ?? [];
        // 检查上传状态， 审核通过的不能再上传
        if (!empty($record) && $record['verify_status'] == SharePosterModel::VERIFY_STATUS_QUALIFIED) {
            throw new RunTimeException(['stop_repeat_upload']);
        }
        if (!empty($record) && $record['verify_status'] == SharePosterModel::VERIFY_STATUS_WAIT) {
            // 待审核 - 更新
            $sqlData = [
                'image_path'       => $params['image_path'],
                'last_upload_time' => $time,
            ];
            MorningSharePosterModel::updateRecord($record['id'], $sqlData);
        } else {
            // 未上传 - 新增
            $sqlData = [
                'student_uuid'     => $studentUuid,
                'image_path'       => $params['image_path'],
                'activity_type'    => MorningTaskAwardModel::MORNING_ACTIVITY_TYPE,
                'verify_status'    => SharePosterModel::VERIFY_STATUS_WAIT,
                'last_upload_time' => $time,
                'create_time'      => $time,
                'task_num'         => $day,
                'ext'              => json_encode([]),
            ];
            MorningSharePosterModel::insertRecord($sqlData);
        }
        return [];
    }

    /**
     * 获取5日打卡开班推送
     * @param $day
     * @return array
     */
    public static function getClockActivityShareRuleMsg($day)
    {
        // 获取推送文案
        $ruleName = '';
        switch ($day) {
            case 1:
                $ruleName = '开班第二天通知';
                break;
            case 2:
                $ruleName = '开班第三天通知';
                break;
            case 3:
                $ruleName = '开班第四天通知';
                break;
        }
        $message = MessagePushRulesModel::getRuleInfo(Constants::QC_APP_ID, $ruleName, MessagePushRulesModel::PUSH_TARGET_ALL);
        if (empty($message)) {
            return [];
        }
        return $message;
    }

    /**
     * 生成5日打卡活动规则海报
     * @param       $studentUuid
     * @param       $day
     * @param array $data
     * @return array
     * @throws RunTimeException
     */
    public static function generateClockActivityRuleMsgPoster($studentUuid, $day, $data = [])
    {
        if (empty($data)) {
            // 获取学生练琴曲目信息
            $studentLesson = (new Morning())->getStudentLessonSchedule([$studentUuid]);
            $lastLesson = [];
            $data = [
                'lesson' => [
                    'report'      => $lastLesson['report'],
                    'lesson_name' => $lastLesson['lesson_name'],
                    'unlock_time' => $lastLesson['unlock_time'],
                ],
            ];
        }
        $message = self::getClockActivityShareRuleMsg($day);
        foreach ($message['content'] as &$item) {
            if ($item['type'] == WeChatConfigModel::CONTENT_TYPE_IMG) {
                $_poster = MorningPushMessageService::generate5DaySharePoster($studentUuid, ['poster_id' => $item['poster_id'], 'path' => $item['value']], $data);
                if (empty($_poster)) {
                    throw new RunTimeException(['eventWechatPushMsgJoinStudent_create_share_poster_error'], [$studentUuid]);
                }
                $item['path'] = $_poster['poster_save_full_path'];
            }
        }
        return $message;
    }
}