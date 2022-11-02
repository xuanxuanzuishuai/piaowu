<?php
/**
 * 清晨5日打卡活动
 */

namespace App\Services\Morning;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Morning;
use App\Libs\MorningDictConstants;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dawn\DawnCollectionModel;
use App\Models\Dawn\DawnLeadsModel;
use App\Models\Erp\ErpEventTaskModel;
use App\Models\MessagePushRulesModel;
use App\Models\MorningSharePosterModel;
use App\Models\MorningTaskAwardModel;
use App\Models\MorningWechatAwardCashDealModel;
use App\Models\OperationActivityModel;
use App\Models\SharePosterModel;
use App\Models\WeChatConfigModel;
use App\Services\CashGrantService;
use App\Services\SharePosterService;

class MorningClockActivityService
{
    // 5日打卡活动解锁状态
    const TASK_STATUS_LOCK           = 0;     // 待解锁、未解锁
    const TASK_STATUS_PROGRESS       = 4; // 进行中
    const TASK_STATUS_NOT_STANDARD   = 5; // 未达标
    const TASK_STATUS_WAIT_VERIFY    = 1;  // 等待审核、审核中
    const TASK_STATUS_VERIFY_SUCCESS = 2;  // 审核通过 - 已打卡
    const TASK_STATUS_VERIFY_FAIL    = 3;  // 审核未通过

    const LOCK_SEND_CLOCK_ACTIVITY_RED_PACK = 'lock_morning_send_clock_activity_red_pack_';

    /**
     * 5日打卡活动详情
     * @param $studentUuid
     * @return array
     */
    public static function getClockActivityIndex($studentUuid)
    {
        $userInfo = (new Morning())->getStudentList([$studentUuid])[0] ?? [];
        $returnData = [
            'uuid'           => $studentUuid,
            'is_join'        => false, // false：不能参与，
            'award_node'     => [],  // 奖励节点
            'node_list'      => [],  // 节点
            'user_status_zh' => MorningDictConstants::get(MorningDictConstants::MORNING_STUDENT_STATUS, $userInfo['status']) ?? '',
        ];
        // 学生线索信息
        $leadsInfo = DawnLeadsModel::getRecord(['uuid' => $studentUuid]);
        if (empty($leadsInfo) || empty($leadsInfo['collection_id'])) {
            SimpleLogger::info("getCollectionActivityDetail_student_collection_is_empty", [$studentUuid]);
            return $returnData;
        }
        // 班级信息
        $collInfo = DawnCollectionModel::getRecord(['id' => $leadsInfo['collection_id']]);
        if (empty($collInfo)) {
            SimpleLogger::info("getCollectionActivityDetail_collection_is_empty", [$studentUuid]);
            return $returnData;
        }
        // 检查班级信息
        try {
            self::checkCollectionInfo($collInfo);
        } catch (RunTimeException $e) {
            SimpleLogger::info("getCollectionActivityDetail_collection_info_error", [$studentUuid, $e->getMessage()]);
            return $returnData;
        }
        $returnData['is_join'] = true;
        list($awardNode, $clockInNode) = MorningDictConstants::get(MorningDictConstants::MORNING_FIVE_DAY_ACTIVITY, ['5day_award_node', '5day_clock_in_node']);
        $awardNode = json_decode($awardNode, true);
        $clockInNode = json_decode($clockInNode, true);
        // 获取奖励列表
        $studentAwardList = MorningTaskAwardModel::getStudentFiveDayAwardList($studentUuid);
        $studentAwardListByDay = array_column($studentAwardList, null, 'task_num');
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
        /**
         * 获取打卡状态
         * 有参与记录以参与记录为准
         * 没有参与记录看是否已解锁
         * 如果已解锁看练琴状态
         */
        // 获取学生练习信息
        $lessonList = (new Morning())->getStudentLessonSchedule([$studentUuid])[$studentUuid] ?? [];
        list($lessonData) = self::getLessonDoneStep($lessonList);
        // 获取参与记录
        $sharePosterList = MorningSharePosterModel::getFiveDayUploadSharePosterList($studentUuid);
        $sharePosterTask = [];
        foreach ($sharePosterList as $item) {
            empty($sharePosterTask[$item['task_num']]) && $sharePosterTask[$item['task_num']] = $item;
        }
        unset($item);
        // 打卡进度
        foreach ($clockInNode as $_node => $_nodeInfo) {
            $unlockTimeUnix = $collInfo['teaching_start_time'] + Util::TIMESTAMP_ONEDAY * $_node;
            $tmpData = [
                'day'         => $_node,
                'task_status' => self::TASK_STATUS_LOCK,
                'unlock_time' => date("Y-m-d 09:i:s", $unlockTimeUnix),
            ];
            /** 是否满足解锁条件 */
            // 是否已参与
            $daySharePoster = $sharePosterTask[$_node] ?? [];
            if (!empty($daySharePoster)) {
                if ($daySharePoster['verify_status'] == SharePosterModel::VERIFY_STATUS_WAIT) {
                    $tmpData['task_status'] = self::TASK_STATUS_WAIT_VERIFY;
                } elseif ($daySharePoster['verify_status'] == SharePosterModel::VERIFY_STATUS_QUALIFIED) {
                    $tmpData['task_status'] = self::TASK_STATUS_VERIFY_SUCCESS;
                } elseif ($daySharePoster['verify_status'] == SharePosterModel::VERIFY_STATUS_UNQUALIFIED) {
                    $tmpData['task_status'] = self::TASK_STATUS_VERIFY_FAIL;
                }
            } else {
                // 是否已完成曲目的练习
                $dayInfo = $lessonData[$_node - 1] ?? [];
                $isDone = $dayInfo['status'] == Constants::STATUS_TRUE;
                if ($unlockTimeUnix <= time()) {
                    // 已解锁， 未完成练琴显示未达标
                    if ($isDone) {
                        $tmpData['task_status'] = self::TASK_STATUS_PROGRESS;
                    } else {
                        $tmpData['task_status'] = self::TASK_STATUS_NOT_STANDARD;
                    }
                }
            }
            $returnData['node_list'][] = $tmpData;
        }
        return $returnData;
    }

    /**
     * 获取5日打卡活动奖励用户侧展示的发放状态
     * 已发放待领取、发放成功 展示 已发放，其他状态都是待发放
     * @param $status
     * @return int
     */
    public static function getCollectionActivityStatus($status)
    {
        if (in_array($status, [OperationActivityModel::SEND_AWARD_STATUS_GIVE, OperationActivityModel::SEND_AWARD_STATUS_GIVE_ING,])) {
            $sendStatus = OperationActivityModel::SEND_AWARD_STATUS_GIVE;
        } else {
            $sendStatus = OperationActivityModel::SEND_AWARD_STATUS_WAITING;
        }
        return $sendStatus;
    }

    /**
     * 获取课程曲目完成情况
     * @param $lessonList
     * @param null $day 第几天从1开始
     * @return array
     */
    public static function getLessonDoneStep($lessonList, $day = 0)
    {
        $returnData = [];
        $dayLesson = [];
        if (empty($lessonList)) return [];
        foreach ($lessonList as $lesson) {
            foreach ($lesson as $key => $item) {
                // 课程状态 1 待解锁 2 待学习 3 已学习 6 学习中
                if ($item['status'] == Constants::STUDENT_LESSON_SCHEDULE_STATUS_DONE) {
                    // 曲目完成练习
                    $_tmpData['status'] = Constants::STATUS_TRUE;
                    if ($key == $day - 1) {
                        $dayLesson = $item;
                    }
                } else {
                    $_tmpData['status'] = Constants::STATUS_FALSE;
                }
                $returnData[] = $_tmpData;
            }
            unset($key, $item);
            // 只处理一个课程的练习曲目， 如果后续增加了练琴曲目，那再调整
            break;
        }
        return [$returnData, $dayLesson];
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
        // 获取参与记录
        $sharePosterRecord = MorningSharePosterModel::getFiveDayUploadSharePosterList($studentUuid, [], $params['day'])[0] ?? 0;
        if (empty($sharePosterRecord)) {
            // 检查当天是否已经解锁  有分班，当天练琴完成
            self::checkClockActivityIsLock($studentUuid, $params['day']);
        }
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
                $returnData['format_verify_reason'] = SharePosterService::formatReason($sharePosterRecord['verify_reason'], $sharePosterRecord['remark']);
            }
        }
        return $returnData;
    }

    /**
     * 检查打卡功能是否解锁
     * @param $studentUuid
     * @param $day
     * @return void
     * @throws RunTimeException
     */
    public static function checkClockActivityIsLock($studentUuid, $day)
    {
        // 检查当天是否已经解锁  有分班，当天练琴完成
        //线索信息
        $leadsInfo = DawnLeadsModel::getRecord(['uuid' => $studentUuid]);
        if (empty($leadsInfo) || empty($leadsInfo['collection_id'])) {
            throw new RunTimeException(['student_collection_is_empty']);
        }
        // 班级信息
        $collInfo = DawnCollectionModel::getRecord(['id' => $leadsInfo['collection_id']]);
        if (empty($collInfo)) {
            throw new RunTimeException(['student_collection_is_empty']);
        }
        // 班级是否解锁
        $unlockTimeUnix = $collInfo['teaching_start_time'] + Util::TIMESTAMP_ONEDAY * $day;
        if ($unlockTimeUnix > time()) {
            throw new RunTimeException(['morning_clock_activity_day_unlock']);
        }
        // 检查班级信息
        self::checkCollectionInfo($collInfo);
        // 获取学生练习信息
        $lessonList = (new Morning())->getStudentLessonSchedule([$studentUuid])[$studentUuid] ?? [];
        list(, $dayLesson) = self::getLessonDoneStep($lessonList, $day);
        // 学生是否练琴
        if (empty($dayLesson) || $dayLesson['status'] != Constants::STUDENT_LESSON_SCHEDULE_STATUS_DONE) {
            throw new RunTimeException(['morning_clock_activity_no_play']);
        }
    }

    /**
     * 检查班级信息是否符合
     * @param $collInfo
     * @return void
     * @throws RunTimeException
     */
    public static function checkCollectionInfo($collInfo)
    {
        $startTime = $collInfo['teaching_start_time'] ?? 0;
        $settingStartTime = MorningDictConstants::get(MorningDictConstants::MORNING_FIVE_DAY_ACTIVITY, '5day_collection_start_time');
        if ($startTime < $settingStartTime) {
            throw new RunTimeException(['morning_clock_activity_collection_time_error']);
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
        // 获取参与记录
        $sharePosterRecord = MorningSharePosterModel::getFiveDayUploadSharePosterList($studentUuid, [], $day)[0] ?? [];
        if (empty($sharePosterRecord)) {
            // 检查当天是否已经解锁  有分班，当天练琴完成
            self::checkClockActivityIsLock($studentUuid, $params['day']);
        }
        // 获取邀请语信息
        $message = self::generateClockActivityRuleMsgPoster($studentUuid, $day);
        if (!empty($message['content']) && is_array($message['content'])) {
            foreach ($message['content'] as $item) {
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
        // 获取参与记录
        $sharePosterRecord = MorningSharePosterModel::getFiveDayUploadSharePosterList($studentUuid, [], $day)[0] ?? [];
        if (empty($sharePosterRecord)) {
            // 检查当天是否已经解锁  有分班，当天练琴完成
            self::checkClockActivityIsLock($studentUuid, $params['day']);
        }
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
            // 未上传或审核未通过 - 新增
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
                $ruleName = 'morning_clock_in_collection_day2';
                break;
            case 2:
                $ruleName = 'morning_clock_in_collection_day3';
                break;
            case 3:
                $ruleName = 'morning_clock_in_collection_day4';
                break;
        }
        $message = MessagePushRulesModel::getRuleInfoByEnName(Constants::QC_APP_ID, $ruleName, MorningPushMessageService::MORNING_PUSH_USER_CLOCK_IN);
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
            $studentLesson = (new Morning())->getStudentLessonSchedule([$studentUuid])[$studentUuid] ?? [];
            list(, $dayLesson) = self::getLessonDoneStep($studentLesson, $day);
            if (!empty($dayLesson)) {
                $data = [
                    'lesson' => [
                        'report'      => $dayLesson['report'],
                        'lesson_name' => $dayLesson['lesson_name'],
                        'unlock_time' => $dayLesson['unlock_time'],
                    ],
                ];
            }
        }
        if (!empty($data)) {
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
        }
        return $message ?? [];
    }

    /**
     * 发放5日打卡活动红包
     * @param array $params
     * @return bool
     */
    public static function sendClockActivityReadPack(array $params)
    {
        SimpleLogger::info('sendClockActivityReadPack_params', $params);
        $lockKey = '';
        try {
            // 接受参数
            $studentUuid = $params['student_uuid'];
            $taskAwardId = $params['task_award_id'];
            $sharePosterRecordId = $params['share_poster_record_id'];
            // 校验参数
            if (empty($studentUuid) || empty($taskAwardId) || empty($sharePosterRecordId)) {
                SimpleLogger::info('sendClockActivityReadPack_params_error', [$studentUuid, $taskAwardId]);
                return false;
            }
            // 只处理审核通过的记录
            $sharePosterRecordInfo = MorningSharePosterModel::getRecord(['id' => $sharePosterRecordId]);
            if (empty($sharePosterRecordInfo) || $sharePosterRecordInfo['verify_status'] != SharePosterModel::VERIFY_STATUS_QUALIFIED) {
                SimpleLogger::info('sendClockActivityReadPack_share_poster_record_error', [$studentUuid, $sharePosterRecordInfo]);
                return false;
            }
            // 获取必要参数 open_id 等信息
            $userOpenid = (new Morning())->getStudentOpenidByUuid([$studentUuid])[$studentUuid] ?? '';
            $lockKey = self::LOCK_SEND_CLOCK_ACTIVITY_RED_PACK . $studentUuid . '-' . $taskAwardId;
            // 加锁 - 失败不处理
            Util::setLock($lockKey);
            // 获取用户红包信息
            $awardData = MorningTaskAwardModel::getRecord([
                'id'           => $taskAwardId,
                'student_uuid' => $studentUuid,
                'award_type'   => ErpEventTaskModel::AWARD_TYPE_CASH,
                'status'       => [OperationActivityModel::SEND_AWARD_STATUS_WAITING, OperationActivityModel::SEND_AWARD_STATUS_GIVE_FAIL, OperationActivityModel::SEND_AWARD_STATUS_GIVE_ING],
            ]);
            if (empty($awardData)) {
                SimpleLogger::info('sendClockActivityReadPack_award_empty', [$studentUuid, $awardData]);
                return false;
            }
            // 金额不大于0-不发放
            if ($awardData['award_amount'] <= 0) {
                SimpleLogger::info('sendClockActivityReadPack_award_amount', [$studentUuid, $awardData]);
                return false;
            }
            // 获取奖励交易信息
            $awardRecordInfo = MorningWechatAwardCashDealModel::getRecord(['user_uuid' => $studentUuid, 'task_award_id' => $taskAwardId]);
            // 发放记录中也必须是 待发放或发放失败才可以发放
            if (!empty($awardRecordInfo) && !in_array($awardRecordInfo['status'], [OperationActivityModel::SEND_AWARD_STATUS_WAITING, OperationActivityModel::SEND_AWARD_STATUS_GIVE_FAIL])) {
                SimpleLogger::info('sendClockActivityReadPack_award_status_error', [$awardData, $awardRecordInfo]);
                return false;
            }
            $now = time();
            // 更新状态
            MorningTaskAwardModel::updateRecord($awardData['id'], [
                'status'       => OperationActivityModel::SEND_AWARD_STATUS_GIVE_ING,
                'operator_id'  => $sharePosterRecordInfo['verify_user'],
                'operate_time' => $now,
                'update_time'  => $now,
            ]);
            // 生成交易号
            $mchBillNo = CashGrantService::genMchBillNo($taskAwardId, $awardRecordInfo, $awardData['award_amount']);
            // 如果交易不存在新增
            if (!empty($awardRecordInfo)) {
                $awardRecordId = $awardRecordInfo['id'];
            } else {
                $recordData = [
                    'task_award_id' => $taskAwardId,
                    'user_uuid'     => $studentUuid,
                    'mch_billno'    => $mchBillNo,
                    'award_amount'  => $awardData['award_amount'],
                    'openid'        => $userOpenid,
                    'status'        => 0,
                    'result_code'   => '',
                    'create_time'   => $now,
                ];
                $awardRecordId = MorningWechatAwardCashDealModel::insertRecord($recordData);
                if (empty($awardRecordId)) {
                    SimpleLogger::info('sendClockActivityReadPack_save_send_record_fail', $recordData);
                    return false;
                }
            }
            // 发送红包
            list($status, $resultCode) = CashGrantService::sendWeChatRedPack($userOpenid, $mchBillNo, $awardData['award_amount'], 'morning_clock_in_red_pack');
            // 更新发放结果
            $updateResData = [
                'mch_billno'  => $mchBillNo,
                'status'      => $status,
                'result_code' => $resultCode,
                'openid'      => $userOpenid,
                'update_time' => time(),
            ];
            $res = MorningWechatAwardCashDealModel::updateRecord($awardRecordId, $updateResData);
            if (empty($res)) {
                SimpleLogger::info('sendClockActivityReadPack_save_send_record_fail', $updateResData);
                return false;
            }
            // 更新发放状态
            $res = MorningTaskAwardModel::updateRecord($awardData['id'], [
                'status'      => $status,
                'update_time' => $now,
            ]);
            if (empty($res)) {
                SimpleLogger::info('sendClockActivityReadPack_save_send_record_a_fail', $updateResData);
                return false;
            }
        } catch (RunTimeException $e) {
            // 抛出异常，记录日志
        } finally {
            // 解锁
            Util::unLock($lockKey);
        }
        return true;
    }
}